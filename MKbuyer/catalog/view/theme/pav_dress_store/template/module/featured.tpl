<?php 
	$cols = 3;
	$span = $cols <= 1 ? 'mode-list' : 'span'.floor(12/$cols); 
?>
<div class="box pav-featured highlight">
  <h3 class="box-heading"><span><?php echo $heading_title; ?></span></h3>
  <div class="box-content">
    <div class="box-product">
			  <?php foreach ($products as $i => $product) {   $i=$i+1; ?>
	<?php if( $i%$cols == 1 && $cols > 1 ) { ?>
	<div class="row-fluid box-product">		
	<?php } ?>
		<div class="product-block <?php echo $span;?>">
	  	<div class="product-inner">
		<?php if ($product['thumb']) { ?>
		<div class="image"><a href="<?php echo $product['href']; ?>"><img src="<?php echo $product['thumb']; ?>" alt="<?php echo $product['name']; ?>" /></a>
			<div class="group-action">
				<div class="btn-overlay cart">
					<input type="button" value="<?php echo $button_cart; ?>" onclick="addToCart('<?php echo $product['product_id']; ?>');" class="button" />									
				</div>
				<div class="btn-overlay wishlist">				
					<a class="pavicon-wishlist" onclick="addToWishList('<?php echo $product['product_id']; ?>');"><?php echo $this->language->get("button_wishlist"); ?></a>
				</div>
				<div class="btn-overlay compare">												
					<a class="pavicon-compare" onclick="addToCompare('<?php echo $product['product_id']; ?>');"><?php echo $this->language->get("button_compare"); ?></a>
				</div>
			</div>
		</div>
		<?php } ?>
		<?php if( isset($product['description']) ){	?>
		<p class="description hiden">
			<?php echo utf8_substr(strip_tags( $product['description']),0,100); ?> ...
		</p>
		<?php } ?>	
	<h4 class="name"><a href="<?php echo $product['href']; ?>"><?php echo $product['name']; ?></a></h4>
		<?php if ($product['rating']) { ?>
			<div class="rating is-hover">
				<img src="catalog/view/theme/<?php echo $this->config->get('config_template');?>/image/stars-<?php echo $product['rating']; ?>.png" alt="<?php echo $product['reviews']; ?>" />
			</div>
		<?php } ?>

		<?php if ($product['price']) { ?>
			<div class="price">
				<?php if (!$product['special']) { ?>
				<?php echo $product['price']; ?>
				<?php } else { ?>
				<span class="price-old"><?php echo $product['price']; ?></span> <span class="price-new"><?php echo $product['special']; ?></span>
				<?php } ?>
			</div>
		<?php } ?>										
	  </div>
	</div>
	<?php if( ($i%$cols == 0 || $i==count($products)) && $cols > 1 ) { ?>
	</div>
	<?php } ?>

	<?php } ?>

    </div>
  </div>
</div>
