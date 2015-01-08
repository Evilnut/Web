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

class ControllerShippingMultiFlatRate extends Controller {
	private $type = 'shipping';
	private $name = 'multi_flat_rate';
	
	public function index() {
		$data = array(
			'type' => $this->type,
			'name' => $this->name,
		);
		
		$this->backupSettings('auto', $data);
		$data['permission'] = $this->user->hasPermission('modify', $this->type . '/' . $this->name);
		
		$token = $data['token'] = (isset($this->session->data['token'])) ? $this->session->data['token'] : '';
		$data = array_merge($data, $this->load->language($this->type . '/' . $this->name));
		$data['exit'] = $this->url->link('extension/' . $this->type, 'token=' . $token, 'SSL');
		
		$data['settings_buttons'] = true;
		$data['tooltips_button'] = true;
		
		//------------------------------------------------------------------------------
		// Data Arrays
		//------------------------------------------------------------------------------
		$data['rule_options'] = array(
			'adjustments'				=> array('max', 'min', 'round', 'tax_class', 'total_value'),
			'cart_criteria'				=> array(),
			'datetime_criteria'			=> array(),
			'location_criteria'			=> array(),
			'order_criteria'			=> array('currency', 'customer_group', 'geo_zone', 'language', 'store'),
			'product_criteria'			=> array(),
			'rule_sets'					=> array(),
		);
		
		$data['currency_array'] = array($this->config->get('config_currency') => '');
		$this->load->model('localisation/currency');
		foreach ($this->model_localisation_currency->getCurrencies() as $currency) {
			$data['currency_array'][$currency['code']] = $currency['code'];
		}
		
		$data['customer_group_array'] = array(0 => $data['text_guests']);
		$this->load->model('sale/customer_group');
		foreach ($this->model_sale_customer_group->getCustomerGroups() as $customer_group) {
			$data['customer_group_array'][$customer_group['customer_group_id']] = $customer_group['name'];
		}
		
		$data['geo_zone_array'] = array(0 => $data['text_everywhere_else']);
		$this->load->model('localisation/geo_zone');
		foreach ($this->model_localisation_geo_zone->getGeoZones() as $geo_zone) {
			$data['geo_zone_array'][$geo_zone['geo_zone_id']] = $geo_zone['name'];
		}
		
		$this->load->model('localisation/language');
		$data['language_array'] = array($this->config->get('config_language') => '');
		$data['language_flags'] = array();
		foreach ($this->model_localisation_language->getLanguages() as $language) {
			$data['language_array'][$language['code']] = $language['name'];
			$data['language_flags'][$language['code']] = $language['image'];
		}
		
		$data['store_array'] = array(0 => $this->config->get('config_name'));
		$store_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "store ORDER BY name");
		foreach ($store_query->rows as $store) {
			$data['store_array'][$store['store_id']] = $store['name'];
		}
		
		$data['tax_class_array'] = array(0 => $data['text_none']);
		$this->load->model('localisation/tax_class');
		foreach ($this->model_localisation_tax_class->getTaxClasses() as $tax_class) {
			$data['tax_class_array'][$tax_class['tax_class_id']] = $tax_class['title'];
		}
		
		$data['total_value_array'] = array();
		foreach (array('prediscounted', 'nondiscounted', 'taxed', 'total') as $total_value) {
			$data['total_value_array'][$total_value] = $data['text_' . $total_value . '_subtotal'];
		}
		
		//------------------------------------------------------------------------------
		// Extensions Settings
		//------------------------------------------------------------------------------
		$data['settings'] = array();
		
		$data['settings'][] = array(
			'key'		=> 'extension_settings',
			'type'		=> 'heading',
		);
		$data['settings'][] = array(
			'key'		=> 'status',
			'type'		=> 'select',
			'options'	=> array('0' => $data['text_disabled'], '1' => $data['text_enabled']),
		);
		$data['settings'][] = array(
			'key'		=> 'sort_order',
			'type'		=> 'text',
			'default'	=> ($this->type == 'shipping' ? '1' : '3'),
			'attributes'=> array('style' => 'width: 30px !important', 'maxlength' => '1'),
		);
		$data['settings'][] = array(
			'key'		=> 'tax_class_id',
			'type'		=> 'select',
			'options'	=> $data['tax_class_array'],
		);
		$data['settings'][] = array(
			'key'		=> 'heading',
			'type'		=> 'multilingual_text',
			'default'	=> $data['heading_title'],
		);
		$data['settings'][] = array(
			'key'		=> 'testing_mode',
			'type'		=> 'select',
			'options'	=> array('0' => $data['text_disabled'], '1' => $data['text_enabled']),
		);
		
		//------------------------------------------------------------------------------
		// Charges
		//------------------------------------------------------------------------------
		$data['settings'][] = array(
			'key'		=> 'charges',
			'type'		=> 'heading',
			'buttons'	=> 'expand_collapse',
		);
		$data['settings'][] = array(
			'type'		=> 'html',
			'content'	=> '<div class="text-info text-center" style="padding-bottom: 25px">' . $data['help_charges'] . '</div>',
		);
		$data['settings'][] = array(
			'key'		=> 'charge',
			'type'		=> 'table_start',
			'columns'	=> array('action', 'group', 'title', 'charge', 'rules'),
		);
		
		$table = 'charge';
		$sortby = 'group';
		foreach ($this->getTableRowNumbers($data, $table, $sortby) as $num => $rules) {
			$prefix = $table . '_' . $num . '_';
			$data['settings'][] = array(
				'type'		=> 'row_start',
			);
			$data['settings'][] = array(
				'key'		=> 'copy',
				'type'		=> 'button',
			);
			$data['settings'][] = array(
				'key'		=> 'delete',
				'type'		=> 'button',
			);
			$data['settings'][] = array(
				'type'		=> 'column',
			);
			$data['settings'][] = array(
				'key'		=> $prefix . 'group',
				'type'		=> 'text',
				'attributes'=> array('style' => 'width: 30px !important', 'maxlength' => '1'),
			);
			$data['settings'][] = array(
				'type'		=> 'column',
			);
			$data['settings'][] = array(
				'key'		=> $prefix . 'title',
				'type'		=> 'multilingual_text',
				'admin_ref'	=> true,
			);
			$data['settings'][] = array(
				'type'		=> 'column',
			);
			$data['settings'][] = array(
				'key'		=> $prefix . 'type',
				'type'		=> 'select',
				'options'	=> array('flat' => $data['text_flat_charge'], 'peritem' => $data['text_per_item_charge']),
			);
			$data['settings'][] = array(
				'key'		=> $prefix . 'charges',
				'type'		=> 'text',
			);
			$data['settings'][] = array(
				'type'		=> 'column',
			);
			$data['settings'][] = array(
				'key'		=> $prefix . 'rule',
				'type'		=> 'rule',
				'rules'		=> $rules,
			);
			$data['settings'][] = array(
				'type'		=> 'row_end',
			);
		}
		
		$data['settings'][] = array(
			'type'		=> 'table_end',
			'buttons'	=> 'add_row',
			'text'		=> 'button_add_charge',
		);
		
		//------------------------------------------------------------------------------
		// end settings
		//------------------------------------------------------------------------------
		
		$this->document->setTitle($data['heading_title']);
		
		if (version_compare(VERSION, '2.0') < 0) {
			$this->data = $data;
			$this->template = $this->type . '/' . $this->name . '.tpl';
			$this->children = array(
				'common/header',	
				'common/footer',
			);
			$this->response->setOutput($this->render());
		} else {
			$data['header'] = $this->load->controller('common/header');
			$data['column_left'] = $this->load->controller('common/column_left');
			$data['footer'] = $this->load->controller('common/footer');
			$this->response->setOutput($this->load->view($this->type . '/' . $this->name . '.tpl', $data));
		}
	}
	
	private function getTableRowNumbers($data, $table, $sorting) {
		$groups = array();
		$rules = array();
		
		foreach ($data['saved'] as $key => $setting) {
			if (preg_match('/' . $table . '_(\d+)_' . $sorting . '/', $key, $matches)) {
				$groups[$setting][] = $matches[1];
			}
			if (preg_match('/' . $table . '_(\d+)_rule_(\d+)_type/', $key, $matches)) {
				$rules[$matches[1]][] = $matches[2];
			}
		}
		
		if (empty($groups)) {
			$groups = array('' => array('1'));
		}
		ksort($groups, SORT_STRING);
		
		$rows = array();
		foreach ($groups as $group) {
			foreach ($group as $num) {
				$rows[$num] = (empty($rules[$num])) ? array() : $rules[$num];
			}
		}
		
		return $rows;
	}
	
	//==============================================================================
	// Setting functions
	//==============================================================================
	private $encryption_key = '';
	
	public function backupSettings($backup_type = '', &$data = array()) {
		$data['saved'] = array();
		
		if (empty($backup_type) && !$this->user->hasPermission('modify', $this->type . '/' . $this->name)) {
			return;
		}
		
		$manual_filepath = DIR_LOGS . $this->name . '_backup' . $this->encryption_key . '.txt';
		$auto_filepath = DIR_LOGS . $this->name . '_autobackup' . $this->encryption_key . '.txt';
		$filepath = ($backup_type == 'auto') ? $auto_filepath : $manual_filepath;
		if (file_exists($filepath)) unlink($filepath);
		
		file_put_contents($filepath, 'EXTENSION	SETTING	NUMBER	SUB-SETTING	SUB-NUMBER	SUB-SUB-SETTING	VALUE' . "\n", FILE_APPEND|LOCK_EX);
		
		$settings_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE `" . (version_compare(VERSION, '2.0.1') < 0 ? 'group' : 'code') . "` = '" . $this->db->escape($this->name) . "' ORDER BY `key` ASC");
		foreach ($settings_query->rows as $setting) {
			$data['saved'][str_replace($this->name . '_', '', $setting['key'])] = (is_string($setting['value']) && strpos($setting['value'], 'a:') === 0) ? unserialize($setting['value']) : $setting['value'];
			
			$parts = explode('|', preg_replace(array('/_(\d+)_/', '/_(\d+)/'), array('|$1|', '|$1'), str_replace($this->name . '_', '', $setting['key'])));
			$line = $this->name . "\t" . $parts[0] . "\t" . (isset($parts[1]) ? $parts[1] : '') . "\t" . (isset($parts[2]) ? $parts[2] : '') . "\t" . (isset($parts[3]) ? $parts[3] : '') . "\t" . (isset($parts[4]) ? $parts[4] : '') . "\t" . str_replace(array("\t", "\n"), array('    ', '\n'), $setting['value']) . "\n";
			
			file_put_contents($filepath, $line, FILE_APPEND|LOCK_EX);
		}
		
		if ($backup_type == 'auto') {
			$data['autobackup_time'] = date('Y-M-d @ g:i a');
			$data['backup_time'] = (file_exists($manual_filepath)) ? date('Y-M-d @ g:i a', filemtime($manual_filepath)) : '';
			asort($data['saved']);
		} elseif (empty($backup_type)) {
			echo date('Y-M-d @ g:i a');
		}
	}
	
	public function viewBackup() {
		if (!$this->user->hasPermission('access', $this->type . '/' . $this->name)) {
			echo 'You do not have permission to view this file.';
			return;
		}
		if (!file_exists(DIR_LOGS . $this->name . '_backup.txt')) {
			echo 'Backup file "' . DIR_LOGS . $this->name . '_backup.txt" does not exist';
			return;
		}
		
		$contents = trim(file_get_contents(DIR_LOGS . $this->name . '_backup' . $this->encryption_key . '.txt'));
		$lines = explode("\n", $contents);
		
		$html = '<table border="1" style="font-family: monospace" cellspacing="0" cellpadding="5">';
		foreach ($lines as $line) {
			$html .= '<tr><td>' . implode('</td><td>', explode("\t", $line)) . '</td></tr>';
		}
		echo $html;
	}
	
	public function downloadBackup() {
		if (!$this->user->hasPermission('access', $this->type . '/' . $this->name) || !file_exists(DIR_LOGS . $this->name . '_backup.txt')) {
			return;
		}
		
		header('Pragma: public');
		header('Expires: 0');
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename=' . $this->name . '_backup.' . date('Y-n-d') . '.txt');
		header('Content-Transfer-Encoding: binary');
		
		echo file_get_contents(DIR_LOGS . $this->name . '_backup' . $this->encryption_key . '.txt');
	}
	
	public function restoreSettings() {
		$data = $this->language->load($this->type . '/' . $this->name);
		
		if (!$this->user->hasPermission('modify', $this->type . '/' . $this->name)) {
			$this->session->data['error'] = $data['standard_error'];
			$this->response->redirect(str_replace(array('&amp;', "\n", "\r"), array('&', '', ''), $this->url->link($this->type . '/' . $this->name, 'token=' . $this->session->data['token'], 'SSL')));
		}
		
		if ($this->request->post['from'] == 'auto') {
			$filepath = DIR_LOGS . $this->name . '_autobackup' . $this->encryption_key . '.txt';
		} elseif ($this->request->post['from'] == 'manual') {
			$filepath = DIR_LOGS . $this->name . '_backup' . $this->encryption_key . '.txt';
		} elseif ($this->request->post['from'] == 'file') {
			$filepath = $this->request->files['backup_file']['tmp_name'];
			if (empty($filepath)) {
				$this->response->redirect(str_replace(array('&amp;', "\n", "\r"), array('&', '', ''), $this->url->link($this->type . '/' . $this->name, 'token=' . $this->session->data['token'], 'SSL')));
			}
		}
		
		$contents = trim(file_get_contents($filepath));
		
		if (strpos($contents, 'EXTENSION') !== 0) {
			$this->session->data['error'] = $data['error_invalid_file_data'];
			$this->response->redirect(str_replace(array('&amp;', "\n", "\r"), array('&', '', ''), $this->url->link($this->type . '/' . $this->name, 'token=' . $this->session->data['token'], 'SSL')));
		}
		
		$this->db->query("DELETE FROM " . DB_PREFIX . "setting WHERE `" . (version_compare(VERSION, '2.0.1') < 0 ? 'group' : 'code') . "` = '" . $this->db->escape($this->name) . "'");
		
		foreach (explode("\n", file_get_contents($filepath)) as $row) {
			if (empty($row) || strpos($row, 'EXTENSION') === 0) continue;
			
			$col = explode("\t", $row);
			$value = str_replace('\n', "\n", array_pop($col));
			$key = implode('_', array_diff($col, array('')));
			
			$this->db->query("INSERT INTO " . DB_PREFIX . "setting SET `store_id` = 0, `" . (version_compare(VERSION, '2.0.1') < 0 ? 'group' : 'code') . "` = '" . $this->db->escape($this->name) . "', `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape($value) . "', `serialized` = 0");
		}
		
		$this->session->data['success'] = $data['text_settings_restored'];
		$this->response->redirect(str_replace(array('&amp;', "\n", "\r"), array('&', '', ''), $this->url->link($this->type . '/' . $this->name, 'token=' . $this->session->data['token'], 'SSL')));
	}
	
	public function saveSetting() {
		if (!$this->user->hasPermission('modify', $this->type . '/' . $this->name)) {
			echo 'PermissionError';
			return;
		}
		
		foreach ($this->request->post as $key => $value) {
			$this->db->query("DELETE FROM " . DB_PREFIX . "setting WHERE `" . (version_compare(VERSION, '2.0.1') < 0 ? 'group' : 'code') . "` = '" . $this->db->escape($this->name) . "'AND `key` = '" . $this->db->escape($this->name . '_' . $key) . "'");
			$this->db->query("
				INSERT INTO " . DB_PREFIX . "setting SET
				`store_id` = 0,
				`" . (version_compare(VERSION, '2.0.1') < 0 ? 'group' : 'code') . "` = '" . $this->db->escape($this->name) . "',
				`key` = '" . $this->db->escape($this->name . '_' . $key) . "',
				`value` = '" . $this->db->escape(stripslashes($value)) . "'
				" . (version_compare(VERSION, '1.5.1') >= 0 ? ", `serialized` = 0" : "") . "
			");
		}
	}
	
	public function deleteSetting() {
		$this->db->query("DELETE FROM " . DB_PREFIX . "setting WHERE `" . (version_compare(VERSION, '2.0.1') < 0 ? 'group' : 'code') . "` = '" . $this->db->escape($this->name) . "' AND `key` = '" . $this->db->escape($this->name . '_' . $this->request->get['setting']) . "'");
	}
}
?>