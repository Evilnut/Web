<?php
//==============================================================================
// Multi Flat Rate Shipping v201.1
// 
// Author: Clear Thinking, LLC
// E-mail: johnathan@getclearthinking.com
// Website: http://www.getclearthinking.com
// 
// All code within this file is copyright Clear Thinking, LLC.
// You may not copy or reuse code within this file without written permission.
//==============================================================================

class ModelShippingMultiFlatRate extends Model {
	private $type = 'shipping';
	private $name = 'multi_flat_rate';
	private $charge;
	
	public function getQuote($address) {
		$settings = $this->getSettings();
		
		if ($settings['testing_mode']) {
			$this->log->write(strtoupper($this->name) . ': ------------------------------ Starting testing mode ------------------------------');
		}
		
		if (empty($settings['status'])) {
			if ($settings['testing_mode']) $this->log->write(strtoupper($this->name) . ': Extension is disabled');
			return;
		}
		
		$language_text = $this->language->load('product/product');
		
		// Set address info
		$addresses = array();
		$this->load->model('account/address');
		foreach (array('shipping', 'payment') as $address_type) {
			if (empty($address) || $address_type == 'payment') {
				$address = array();
				
				if ($this->customer->isLogged()) 										$address = $this->model_account_address->getAddress($this->customer->getAddressId());
				if (!empty($this->session->data['country_id']))							$address['country_id'] = $this->session->data['country_id'];
				if (!empty($this->session->data['zone_id']))							$address['zone_id'] = $this->session->data['zone_id'];
				if (!empty($this->session->data['postcode']))							$address['postcode'] = $this->session->data['postcode'];
				if (!empty($this->session->data['city']))								$address['city'] = $this->session->data['city'];
				
				if (!empty($this->session->data[$address_type . '_country_id']))		$address['country_id'] = $this->session->data[$address_type . '_country_id'];
				if (!empty($this->session->data[$address_type . '_zone_id']))			$address['zone_id'] = $this->session->data[$address_type . '_zone_id'];
				if (!empty($this->session->data[$address_type . '_postcode']))			$address['postcode'] = $this->session->data[$address_type . '_postcode'];
				if (!empty($this->session->data[$address_type . '_city']))				$address['city'] = $this->session->data[$address_type . '_city'];
				
				if (!empty($this->session->data['guest'][$address_type]))				$address = $this->session->data['guest'][$address_type];
				if (!empty($this->session->data['guest'][$address_type.'_address']))	$address = $this->session->data['guest'][$address_type];
				if (!empty($this->session->data[$address_type . '_address_id']))		$address = $this->model_account_address->getAddress($this->session->data[$address_type . '_address_id']);
				if (!empty($this->session->data[$address_type . '_address']))			$address = $this->session->data[$address_type.'_address'];
				
				if (empty($address['address_1']))										$address['address_1'] = '';
				if (empty($address['address_2']))										$address['address_2'] = '';
				if (empty($address['city']))											$address['city'] = '';
				if (empty($address['postcode']))										$address['postcode'] = '';
				if (empty($address['country_id']))										$address['country_id'] = $this->config->get('config_country_id');
				if (empty($address['zone_id']))											$address['zone_id'] =  $this->config->get('config_zone_id');
				
				$country_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "country WHERE country_id = " . (int)$address['country_id']);
				$address['country'] = (isset($country_query->row['name'])) ? $country_query->row['name'] : '';
				$address['iso_code_2'] = (isset($country_query->row['iso_code_2'])) ? $country_query->row['iso_code_2'] : '';
				
				$zone_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone WHERE zone_id = " . (int)$address['zone_id']);
				$address['zone'] = (isset($zone_query->row['name'])) ? $zone_query->row['name'] : '';
				$address['zone_code'] = (isset($zone_query->row['code'])) ? $zone_query->row['code'] : '';
			}
			
			$addresses[$address_type] = $address;
			
			$addresses[$address_type]['geo_zones'] = array();
			$geo_zones_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE country_id = " . (int)$address['country_id'] . " AND (zone_id = 0 OR zone_id = " . (int)$address['zone_id'] . ")");
			if ($geo_zones_query->num_rows) {
				foreach ($geo_zones_query->rows as $geo_zone) {
					$addresses[$address_type]['geo_zones'][] = $geo_zone['geo_zone_id'];
				}
			} else {
				$addresses[$address_type]['geo_zones'] = array(0);
			}
		}
		
		// Set order totals if necessary
		if ($this->type != 'total') {
			$order_totals_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "extension WHERE `type` = 'total' ORDER BY `code` ASC");
			$order_totals = $order_totals_query->rows;
			
			$sort_order = array();
			foreach ($order_totals as $key => $value) {
				$sort_order[$key] = $this->config->get($value['code'] . '_sort_order');
			}
			array_multisort($sort_order, SORT_ASC, $order_totals);
			
			$total_data = array();
			$order_total = 0;
			$taxes = $this->cart->getTaxes();
			
			foreach ($order_totals as $ot) {
				if ($ot['code'] == $this->type) break;
				if (!$this->config->get($ot['code'] . '_status')) continue;
				$this->load->model('total/' . $ot['code']);
				$this->{'model_total_' . $ot['code']}->getTotal($total_data, $order_total, $taxes);
			}
		}
		
		// Loop through charges
		$cart_products = $this->cart->getProducts();
		$currency = $this->session->data['currency'];
		$customer_group_id = (version_compare(VERSION, '2.0') < 0) ? (int)$this->customer->getCustomerGroupId() : (int)$this->customer->getGroupId();
		$default_currency = $this->config->get('config_currency');
		$language = $this->session->data['language'];
		$store_id = (int)$this->config->get('config_store_id');
		
		$this->load->model('catalog/product');
		$charges = array();

		foreach ($settings['charge'] as $charge) {
			$charge['testing_mode'] = $settings['testing_mode'];
			$charge['title'] = (!empty($charge['title_admin'])) ? $charge['title_admin'] : $charge['title_' . $language];
			if (empty($charge['type'])) {
				$charge['type'] = str_replace(array('_based', '_fee'), '', $this->name);
			}
			$this->charge = $charge;
			
			if (empty($charge['group'])) {
				if ($this->charge['testing_mode']) {
					$this->log->write(strtoupper($this->name) . ': "' . $this->charge['title'] . '" disabled because it has an empty Sort Order value');
				}
				continue;
			}
			
			// Compile rules and rule sets
			$rule_list = (!empty($charge['rule'])) ? $charge['rule'] : array();
			$rule_sets = array();
			
			foreach ($rule_list as $rule) {
				if (isset($rule['type']) && $rule['type'] == 'rule_set') {
					$rule_sets[] = $settings['rule_set'][$rule['value']]['rule'];
				}
			}
			
			foreach ($rule_sets as $rule_set) {
				$rule_list = array_merge($rule_list, $rule_set);
			}
			
			$rules = array();
			foreach ($rule_list as $rule) {
				if (empty($rule['type'])) continue;
				$rules[$rule['type']][$rule['comparison']][] = (isset($rule['value'])) ? $rule['value'] : 1;
			}
			$this->charge['rules'] = $rules;
			
			// Perform settings overrides
			$defaults = array();
			if (isset($rules['setting_override'])) {
				foreach ($rules['setting_override'] as $setting => $override) {
					$defaults[$setting] = $this->config->get($setting);
					$this->config->set($setting, $override[0]);
					
					if ($setting == 'config_address') {
						$distance = 0;
					}
				}
			}
			
			// Set location information
			if (isset($rules['location_comparison'])) {
				$location_comparison = $rules['location_comparison'];
			} else {
				$location_comparison = ($this->type == 'shipping' || empty($addresses['payment']['postcode'])) ? 'shipping' : 'payment';
			}
			$address = $addresses[$location_comparison];
			$postcode = $address['postcode'];
			
			// Check order criteria
			if ($this->ruleViolation('currency', $currency) ||
				$this->ruleViolation('customer_group', $customer_group_id) ||
				$this->ruleViolation('geo_zone', $address['geo_zones']) ||
				$this->ruleViolation('language', $language) ||
				$this->ruleViolation('store', $store_id)
			) {
				continue;
			}
			
			// Generate comparison values
			$cart_criteria = array(
				'quantity',
				'total',
			);
			
			foreach ($cart_criteria as $spec) {
				${$spec.'s'} = array();
				if (isset($rules[$spec])) {
					$this->commaMerge($rules[$spec]);
				}
			}
			
			$product_keys = array();
			
			foreach ($cart_products as $product) {
				if ($this->type == 'shipping' && !$product['shipping']) {
					$order_total -= $product['total'];
					if ($this->charge['testing_mode']) {
						$this->log->write(strtoupper($this->name) . ': ' . $product['name'] . ' (product_id: ' . $product['product_id'] . ') does not require shipping and was ignored');
					}
					continue;
				}
				
				$product_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product WHERE product_id = " . (int)$product['product_id']);
				
				// quantity
				$quantitys[$product['key']] = $product['quantity'];
				
				// total
				if (isset($rules['total_value'])) {
					$product_info = $this->model_catalog_product->getProduct($product['product_id']);
					$product_price = ($product_info['special']) ? $product_info['special'] : $product_info['price'];
					
					if ($rules['total_value'][''][0] == 'prediscounted') {
						$totals[$product['key']] = $product['total'] + ($product['quantity'] * ($product_query->row['price'] - $product_price));
					} elseif ($rules['total_value'][''][0] == 'nondiscounted') {
						$totals[$product['key']] = ($product_info['special']) ? 0 : $product['total'];
					} elseif ($rules['total_value'][''][0] == 'taxed') {
						$totals[$product['key']] = $this->tax->calculate($product['total'], $product['tax_class_id']);
					}
				} else {
					$totals[$product['key']] = $product['total'];
				}
				
				// product passed all rules and is eligible for charge
				$product_keys[] = $product['key'];
			}
			
			if (empty($product_keys)) {
				if ($this->charge['testing_mode']) {
					$this->log->write(strtoupper($this->name) . ': "' . $this->charge['title'] . '" disabled for having no eligible products');
				}
				continue;
			}
			
			$single_foreign_currency = (isset($rules['currency']['is']) && count($rules['currency']['is']) == 1 && $default_currency != $currency) ? $rules['currency']['is'][0] : '';
			
			// Check cart criteria and generate total comparison values
			foreach ($cart_criteria as $spec) {
				// note: cart_comparison to be added here if requested
				if ($spec == 'total' && isset($rules['total_value']) && $rules['total_value'][''][0] == 'shipping_cost') {
					$total = $shipping_cost;
					$cart_total = $shipping_cost;
				} elseif ($spec == 'total' && isset($rules['total_value']) && $rules['total_value'][''][0] == 'total') {
					$total = $order_total;
					$cart_total = $order_total;
				} else {
					${$spec} = 0;
					foreach ($product_keys as $product_key) {
						${$spec} += ${$spec.'s'}[$product_key];
					}
					${'cart_'.$spec} = array_sum(${$spec.'s'});
				}
				
				if ($spec == 'total' && $single_foreign_currency) {
					$total = $this->currency->convert($total, $default_currency, $single_foreign_currency);
				}
			}
			
			// Calculate the charge
			$this->charge['testing_mode'] = false;
			$brackets = (!empty($charge['charges'])) ? array($charge['charges'] . ($charge['type'] == 'peritem' ? ' / 1' : '')) : array(0);
			$charge['type'] = 'quantity';
			
			$cost = $this->calculateBrackets($brackets, $charge['type'], ${$charge['type']}, $quantity, $total);
			
			if ($cost === false) {
				if ($settings['testing_mode']) {
					$this->log->write(strtoupper($this->name) . ': "' . $this->charge['title'] . '" disabled for not matching any of "' . implode(', ', $brackets) . '"');
				}
				continue;
			}
			
			// Adjust charge
			if (isset($rules['adjust']['charge'])) {
				foreach ($rules['adjust']['charge'] as $adjustment) {
					$cost += (strpos($adjustment, '%')) ? $cost * (float)$adjustment / 100 : (float)$adjustment;
				}
			}
			if (isset($rules['round'])) {
				foreach ($rules['round'] as $comparison => $values) {
					$round = $values[0];
					if ($comparison == 'nearest') {
						$cost = round($cost / $round) * $round;
					} elseif ($comparison == 'up') {
						$cost = ceil($cost / $round) * $round;
					} elseif ($comparison == 'down') {
						$cost = floor($cost / $round) * $round;
					}
				}
			}
			if (isset($rules['min'])) {
				$cost = max($cost, $rules['min'][''][0]);
			}
			if (isset($rules['max'])) {
				$cost = min($cost, $rules['max'][''][0]);
			}
			/*
			if (isset($rules['currency']['is']) && $convert_based_on_first_currency_in_list) {
				$cost = $this->currency->convert($total, $rules['currency']['is'][0], $currency);
			}
			$cost = $this->currency->convert($cost, $currency, $default_currency);
			*/
			
			// Add to charge array
			$charges[strtolower($charge['group'])][] = array(
				'title'			=> str_replace('[' . $charge['type'] . ']', round(${$charge['type']}, 2), html_entity_decode($charge['title_' . $language], ENT_QUOTES, 'UTF-8')),
				'charge'		=> (float)$cost,
				'tax_class_id'	=> (isset($rules['tax_class']) ? $rules['tax_class'][''][0] : !empty($settings['tax_class_id']) ? $settings['tax_class_id'] : 0),
			);
			
			// Restore setting defaults
			foreach ($defaults as $key => $value) {
				$this->config->set($key, $value);
			}
			
		} // end charge loop
		
		$quote_data = array();
		
		foreach ($charges as $group_value => $group) {
			foreach ($group as $rate) {
				if ($this->type == 'shipping' && $rate['charge'] < 0) continue;
				
				$taxed_charge = $this->tax->calculate($rate['charge'], $rate['tax_class_id'], $this->config->get('config_tax'));
				
				$quote_data[$this->name . '_' . count($quote_data)] = array(
					'code'			=> $this->name . '.' . $this->name . '_' . count($quote_data),
					'sort_order'	=> $group_value,
					'title'			=> $rate['title'],
					'cost'			=> $rate['charge'],
					'value'			=> $rate['charge'],
					'tax_class_id'	=> $rate['tax_class_id'],
					'text'			=> $this->currency->format($taxed_charge) . ($taxed_charge == $rate['charge'] ? '' : ' (' . $language_text['text_tax'] . ' ' . $this->currency->format($rate['charge']) . ')'),
				);
			}
		}
		
		$sort_order = array();
		foreach ($quote_data as $key => $value) $sort_order[$key] = $value['sort_order'];
		array_multisort($sort_order, SORT_ASC, $quote_data);
		
		foreach ($quote_data as $quote) {
			$total_data[] = $quote_data;
			
			if ($quote['tax_class_id']) {
				foreach ($this->tax->getRates($quote['cost'], $quote['tax_class_id']) as $tax_rate) {
					$taxes[$tax_rate['tax_rate_id']] = (isset($taxes[$tax_rate['tax_rate_id']])) ? $taxes[$tax_rate['tax_rate_id']] + $tax_rate['amount'] : $tax_rate['amount'];
				}
			}
			
			$order_total += $quote['cost'];
		}
		
		if ($settings['testing_mode']) {
			$this->log->write(strtoupper($this->name) . ': ------------------------------ Ending testing mode ------------------------------');
		}
		
		if ($this->type == 'shipping') {
			return array(
				'code'			=> $this->name,
				'title'			=> html_entity_decode($settings['heading_' . $language], ENT_QUOTES, 'UTF-8'),
				'quote'			=> $quote_data,
				'sort_order'	=> $settings['sort_order'],
				'error'			=> false
			);
		}
	}
	
	//------------------------------------------------------------------------------
	// Private functions
	//------------------------------------------------------------------------------
	private function getSettings() {
		$settings = array();
		$settings_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE `" . (version_compare(VERSION, '2.0.1') < 0 ? 'group' : 'code') . "` = '" . $this->db->escape($this->name) . "' ORDER BY `key` ASC");
		
		foreach ($settings_query->rows as $setting) {
			$value = (is_string($setting['value']) && strpos($setting['value'], 'a:') === 0) ? unserialize($setting['value']) : $setting['value'];
			$split_key = preg_split('/_(\d+)_?/', str_replace($this->name . '_', '', $setting['key']), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
			
			if (count($split_key) == 1) {
				$settings[$split_key[0]] = $value;
			} elseif (count($split_key) == 2) {
				$settings[$split_key[0]][$split_key[1]] = $value;
			} elseif (count($split_key) == 3) {
				$settings[$split_key[0]][$split_key[1]][$split_key[2]] = $value;
			} elseif (count($split_key) == 4) {
				$settings[$split_key[0]][$split_key[1]][$split_key[2]][$split_key[3]] = $value;
			} else {
				$settings[$split_key[0]][$split_key[1]][$split_key[2]][$split_key[3]][$split_key[4]] = $value;
			}
		}
		
		return $settings;
	}
	
	private function commaMerge(&$rule) {
		$merged_rule = array();
		foreach ($rule as $comparison => $values) {
			$merged_rule[$comparison] = array();
			foreach ($values as $value) {
				$merged_rule[$comparison] = array_merge($merged_rule[$comparison], explode(',', strtolower($value)));
			}
		}
		$rule = $merged_rule;
	}
	
	private function ruleViolation($rule, $value) {
		$violation = false;
		$rules = $this->charge['rules'];
		$function = (is_array($value)) ? 'array_intersect' : 'in_array';
		
		if (isset($rules[$rule]['after']) && strtotime($value) < min(array_map('strtotime', $rules[$rule]['after']))) {
			$violation = true;
			$comparison = 'after';
		}
		if (isset($rules[$rule]['before']) && strtotime($value) > max(array_map('strtotime', $rules[$rule]['before']))) {
			$violation = true;
			$comparison = 'before';
		}
		if (isset($rules[$rule]['is']) && !$function($value, $rules[$rule]['is'])) {
			$violation = true;
			$comparison = 'is';
		}
		if (isset($rules[$rule]['not']) && $function($value, $rules[$rule]['not'])) {
			$violation = true;
			$comparison = 'not';
		}
		
		if ($this->charge['testing_mode'] && $violation) {
			$this->log->write(strtoupper($this->name) . ': "' . $this->charge['title'] . '" disabled for violating rule "' . $rule . ' ' . $comparison . ' ' . implode(', ', $rules[$rule][$comparison]) . '" with value "' . (is_array($value) ? implode(',', $value) : $value) . '"');
		}
		
		return $violation;
	}
	
	private function inRange($value, $range_list, $type = '') {
		$in_range = false;
		
		foreach ($range_list as $range) {
			if ($range == '') continue;
			
			$range = (strpos($range, '::')) ? explode('::', $range) : explode('-', $range);
			
			if (strpos($type, 'distance') === 0) {
				if (empty($range[1])) {
					array_unshift($range, 0);
				}
				
				$distance = (strpos($range[1], 'km')) ? $value *= 1.609344 : $value;
				
				if ($distance >= (float)$range[0] && $distance <= (float)$range[1]) {
					$in_range = true;
				}
			} elseif (strpos($type, 'postcode') === 0) {
				$postcode = preg_replace('/[^A-Z0-9]/', '', strtoupper($value));
				$from = preg_replace('/[^A-Z0-9]/', '', strtoupper($range[0]));
				$to = (isset($range[1])) ? preg_replace('/[^A-Z0-9]/', '', strtoupper($range[1])) : $from;
				
				if (strlen($from) < strlen($postcode)) $from = str_pad($from, strlen($from) + 3, ' ');
				if (strlen($to) < strlen($postcode)) $to = str_pad($to, strlen($to) + 3, preg_match('/[A-Z]/', $postcode) ? 'Z' : '9');
				
				$postcode = substr_replace(substr_replace($postcode, ' ', -3, 0), ' ', -2, 0);
				$from = substr_replace(substr_replace($from, ' ', -3, 0), ' ', -2, 0);
				$to = substr_replace(substr_replace($to, ' ', -3, 0), ' ', -2, 0);
				
				if (strnatcasecmp($postcode, $from) >= 0 && strnatcasecmp($postcode, $to) <= 0) {
					$in_range = true;
				}
			} else {
				if ($type != 'option' && $type != 'other product data' && !isset($range[1])) {
					$range[1] = 999999999;
				}
				
				if ((count($range) > 1 && $value >= $range[0] && $value <= $range[1]) || (count($range) == 1 && $value == $range[0])) {
					$in_range = true;
				}
			}
		}
		
		if ($this->charge['testing_mode'] && (strpos($type, ' not') ? $in_range : !$in_range)) {
			$this->log->write(strtoupper($this->name) . ': "' . $this->charge['title'] . '" disabled for violating rule "' . $type . (strpos($type, ' not') ? ' is not ' : ' is ') . implode(', ', $range_list) . '" with value "' . $value . '"');
		}
		
		return $in_range;
	}
	
	private function calculateBrackets($brackets, $charge_type, $comparison_value, $quantity, $total) {
		$to = 0;
		
		foreach ($brackets as $bracket) {
			$bracket = str_replace(array('::', ':'), array('-', '='), $bracket);
			
			$bracket_pieces = explode('=', $bracket);
			if (count($bracket_pieces) == 1) {
				array_unshift($bracket_pieces, ($charge_type == 'postcode') ? '0-ZZZZ' : '0-999999');
			}
			
			$from_and_to = explode('-', $bracket_pieces[0]);
			if (count($from_and_to) == 1) {
				array_unshift($from_and_to, ($charge_type == 'postcode') ? $from_and_to[0] : $to);
			}
			$from = trim($from_and_to[0]);
			$to = trim($from_and_to[1]);
			
			$cost_and_per = explode('/', $bracket_pieces[1]);
			$per = (isset($cost_and_per[1])) ? (float)$cost_and_per[1] : 0;
			
			$top = min($to, $comparison_value);
			$bottom = (isset($this->charge['rules']['cumulative'])) ? $from : 0;
			$difference = ($charge_type == 'postcode') ? $quantity : $top - $bottom;
			$multiplier = ($per) ? ceil($difference / $per) : 1;
			
			if (!isset($cost) || !isset($this->charge['rules']['cumulative'])) {
				$cost = 0;
			}
			$cost += (strpos($cost_and_per[0], '%')) ? (float)$cost_and_per[0] * $multiplier * $total / 100 : (float)$cost_and_per[0] * $multiplier;
			
			$in_range = $this->inRange($comparison_value, array($from . '-' . $to), $charge_type);
			if ($in_range) {
				return $cost;
			}
		}
		
		return false;
	}
}
?>