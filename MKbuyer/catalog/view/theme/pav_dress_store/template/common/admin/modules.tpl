<?php 
$d = array(
		'demo_widget_about_data' => '
		<div class="about-us">
			<h3>About us</h3>

			<p>Lorem ipsum dolor sit amet consectetur adipiscing nulla facilisis interdum venenatis nullam vulputate cursus nisi quis volutpat curabitur id mauris a ante volutpat varius.</p>
			</div>

			<div class="social">
			<ul>
				<li class="pinterest"><a href="#"><span class="icon-pinterest stack"><span>Printerest</span></span></a></li>
				<li class="google"><a href="#"><span class="icon-google-plus stack"><span>Google Plus</span></span></a></li>
				<li class="facebook"><a href="#"><span class="icon-facebook stack"><span>Facebook</span></span></a></li>
				<li class="twitter"><a class="twitter" href="#"><span class="icon-twitter stack"><span>Twitter</span></span></a></li>
			</ul>
			</div>
			
	',
	
	);

$module = array_merge( $d, $module );

//	echo '<pre>'.print_r( $languages, 1 );die;

?>

<div class="inner-modules-wrap">
	<div class="vtabs mytabs" id="my-tab-innermodules">
		<a onclick="return false;" href="#tab-imodule-footer" class="selected"><?php echo $this->language->get('Footer Center');?></a>
	 </div>

	 <div class="page-tabs-wrap">
	 		<div id="tab-imodule-footer">

				<h4><?php echo $this->language->get( 'About Module' ) ; ?></h4>

				<div id="language-widget_about_data" class="htabs mytabstyle">
		            <?php foreach ($languages as $language) { ?>
		            <a href="#tab-language-widget_about_data-<?php echo $language['language_id']; ?>"><img src="view/image/flags/<?php echo $language['image']; ?>" title="<?php echo $language['name']; ?>" /> <?php echo $language['name']; ?></a>
		            <?php } ?>
		          </div>

				<?php foreach ($languages as $language) {   ?>
		          <div id="tab-language-widget_about_data-<?php echo $language['language_id']; ?>">
		           
		            <table class="form">
		            	<?php $text =  isset($module['widget_about_data'][$language['language_id']]) ? $module['widget_about_data'][$language['language_id']] : $module['demo_widget_about_data'];  ?>

		              <tr>
		                <td><img src="view/image/flags/<?php echo $language['image']; ?>" title="<?php echo $language['name']; ?>" /> <?php echo $this->language->get('about');?>: </td>
		                <td><textarea name="themecontrol[widget_about_data][<?php echo $language['language_id']; ?>]" id="widget_about_data-<?php echo $language['language_id']; ?>" rows="5" cols="60"><?php echo $text; ?></textarea></td>
		              </tr>
		            </table>
		          </div>
		          <?php } ?>
				

			<script type="text/javascript" src="view/javascript/ckeditor/ckeditor.js"></script> 
				<script type="text/javascript"><!--
				$("#language-widget_about_data a").tabs();
				<?php foreach( $languages as $language )  { ?>
				CKEDITOR.replace('widget_about_data-<?php echo $language['language_id']; ?>', {
					filebrowserBrowseUrl: 'index.php?route=common/filemanager&token=<?php echo $token; ?>',
					filebrowserImageBrowseUrl: 'index.php?route=common/filemanager&token=<?php echo $token; ?>',
					filebrowserFlashBrowseUrl: 'index.php?route=common/filemanager&token=<?php echo $token; ?>',
					filebrowserUploadUrl: 'index.php?route=common/filemanager&token=<?php echo $token; ?>',
					filebrowserImageUploadUrl: 'index.php?route=common/filemanager&token=<?php echo $token; ?>',
					filebrowserFlashUploadUrl: 'index.php?route=common/filemanager&token=<?php echo $token; ?>'
				});
				<?php } ?>
				//--></script> 

			</div>
		</div>
		<div class="clearfix clear"></div>	
	</div>

