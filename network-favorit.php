<?php
/** 
Plugin Name: Network-Favorite
Plugin URI: http://thobian.info/?page_id=1217
Version: 1.0
Author: 晴天打雨伞
Description: 使用Network-Favorites，可以快速将您看到的网页收藏到自己博客，方便以后阅读，同时还可以将自己收藏的内容分享给网友。
Author URI: http://thobian.info
*/

//插件前期准备，新增数据表（favorites）
register_activation_hook(__FILE__, 'wp_favorite_install');
$fav_db_version = '1.0';
function wp_favorite_install () {
	global $wpdb;
	global $fav_db_version;

	$table_name = $wpdb->prefix . 'favorites';
	if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
		$sql = 'CREATE TABLE `' . $table_name . "` (
				`id` int(10) unsigned NOT NULL auto_increment,
				`fav_author` bigint(20) unsigned NOT NULL default '0',
				`fav_date` int(10) unsigned NOT NULL,
				`fav-cateid` int(10) unsigned NOT NULL default '0',
				`fav_title` varchar(500) NOT NULL,
				`fav_url` varchar(500) NOT NULL,
				`fav_share` tinyint(3) unsigned NOT NULL default '1' COMMENT '0unshare, 1share',
				PRIMARY KEY  (`id`)
				) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;";
		
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		
		global $userdata;
		get_currentuserinfo();
		$title = 'WP-favorites Home Page';
		$url   = 'http://thobian.info/?page_id=1217';
		$author= $userdata->ID;
		$date  = time();
		$share = 1;
		$insert= 'INSERT INTO '.$wpdb->prefix.'favorites(id, fav_author, fav_date, fav_title, fav_url, fav_share)'
				."VALUES(NULL, {$author}, {$date}, '{$title}', '{$url}', $share)";
				$rs	   = $wpdb->query($sql);
		$results = $wpdb->query( $insert );
		add_option("fav_db_version", $fav_db_version);
	}
}

//添加css
add_action('wp_head', 'load_wp_favorite_css');
function  load_wp_favorite_css(){
	$url  = get_option('siteurl') . '/wp-content/plugins/wp-favorite';
	$path = ABSPATH. '/wp-content/plugins/wp-favorite';
	if(file_exists($path. "/wp-favorite.css")){
		$css_url = $url . "/wp-favorite.css";
		echo "\n".'<link rel="stylesheet" href="' . $css_url . '" type="text/css"  />'."\n";
	}
}


//前台查看收藏文章
add_filter('the_content', 'wp_favorite_list', 1);
function  wp_favorite_list( $content ){
	if( is_page() ){
		global $wpdb;
		
		preg_match('/\[wp_favorites limit=[\'"](.*)?[\'"] \/\]/i', $content, $wp_favorites);
		$limit	= $wp_favorites[1];
		$page	= isset( $_GET['paged']) && intval($_GET['paged'])>1 ? intval($_GET['paged']) : 1;
		$offset	= ($page-1)*$limit;
		$where	= ' WHERE fav_share=1 ';
		$orderby= ' ORDER BY fav_date DESC';
		$sql	= 'SELECT * FROM '.$wpdb->prefix.'favorites '.$where." $orderby LIMIT {$offset}, {$limit}"; 
		$result	= $wpdb->get_results( $sql, ARRAY_A);
		$count 	= count($result);

		$favorites = '<div id="wp_favorite">';
		foreach($result as $key=>$value){
			$class	   = $count==$key+1 ? 'last' : '';
			$favorites.= '<div class="wp_favorite_item '.$class.'">';
			$favorites.= '<h3 class="wp_favorite_hd"><a href="'.$value['fav_url'].'" target="_blank" rel="index nofollow">'. strip_tags($value['fav_title']).'</a></h3>';
			$favorites.= '<div class="wp_favorite_dt">';
			$favorites.= '<span class="wp_favorite_time">Post at : '.date(get_option('date_format'), $value['fav_date']).'</span>';
			$favorites.= '</div>';
			$favorites.= '</div>';
		}
		$favorites.= '</div>';
		$content = str_replace($wp_favorites[0], $favorites, $content);
		//翻页
		$sql	 = 'SELECT COUNT(*) AS total FROM '.$wpdb->prefix.'favorites '.$where;
		$total	 = $wpdb->get_results( $sql, ARRAY_A);
		$cururl  = get_permalink();
		$content.= wp_favorite_turnpage($cururl, $page, $total[0]['total'], $limit);
	}
	return $content;
}
//翻页函数
function   wp_favorite_turnpage($cururl, $curpage, $total, $limit){
	$turnpage = '';
	if( $total>0 && $total>$limit){
		$count = 10;
		$pages = ceil($total/$limit);

		if( $pages<=$count ){
			$from = 1;
			$to	  = $pages;
		}else{
			$from = $curpage - floor($count/2)+1;
			$to   = $curpage + ceil($count/2);
			if( $from<1 ){
				$from = 1;
				$to   = $count;
			}else if( $to>$pages ){
				$from = $count-1;
				$to   = $pages;
			}
		}
		$turnpage = '<div style="margin:10px auto;">';
		if( $from!=1 ){
			$turnpage .= '<a href="'.$cururl.'&paged='.($to-1).'" rel="prev" style="padding:3px 8px; border:1px solid #ccc; margin:0 5px;">Prev</a>';
		}
		for($from ; $from<=$to; $from++){
			if( $from==$curpage ){
				$turnpage .= '<a class="current" href="javascript:void(0);" style="padding:3px 8px; border:1px solid #ccc; margin:0 5px; background-color:#efefef; text-decoration:none;">'.$from.'</a>';
			}else{
				$turnpage .= '<a href="'.$cururl.'&paged='.($from).'" style="padding:3px 8px; border:1px solid #ccc; margin:0 5px;">'.$from.'</a>';
			}
		}
		if( $to!=$pages ){
			$turnpage .= '<a href="'.$cururl.'&paged='.($to+1).'" rel="next" style="padding:3px 8px; border:1px solid #ccc; margin:0 5px;">Next</a>';
		}
		$turnpage .= '</div>';
	}
	return $turnpage;
}


//添加后台设置菜单
add_action('admin_menu', 'wp_favorite_menu');
function wp_favorite_menu() {
	add_options_page( 'WP-favorite', 'WP-favorite', 8, 'wp_favorite', 'wp_favorite_subpane');
}

function wp_favorite_subpane() {
	global $wpdb;
	$message = null;
	if( isset($_POST['wp_fav_submit']) ){
		global $userdata;
		get_currentuserinfo();
		
		$title = trim($_POST['wp_fav_title']);
		$url   = trim($_POST['wp_fav_url']);
		$title = $wpdb->escape($title);
		$url   = $wpdb->escape($url);
		$author= $userdata->ID;
		$date  = time();
		$share = 1;
		
		$sql   = 'INSERT INTO '.$wpdb->prefix.'favorites(id, fav_author, fav_date, fav_title, fav_url, fav_share)' 
				  ."VALUES(NULL, {$author}, {$date}, '{$title}', '{$url}', $share)";
		$rs	   = $wpdb->query($sql);
		$message = $rs ? '添加成功！' : '添加失败！';
	}else{
		$title = isset($_GET['title']) ? $_GET['title'] : null;
		$url   = isset($_GET['url']) ? $_GET['url'] : null;
	}
	if( $title && $url ){
?>
	<style type="text/css">
		.wpfav-first{width:100px; text-align:right;}
	</style>
	<div class="wrap">
		<?php if($message){?>
		<div id="message" class="updated fade"><p><?php echo $message; ?></p></div>
		<?php } ?>
		<form method="post" action="?page=wp_favorite" id="wp_fav">
        <h3>Add a new web page</h3>
        <table class="form-table">
		  <tr>
            <td class="wpfav-first">Title:</td>
            <td>
              <input type="text" name="wp_fav_title" value="<?php echo $title; ?>" class="regular-text" id="wp_fav_title" />
            </td>
          </tr>
		  <tr>
            <td class="wpfav-first">URL:</td>
            <td>
              <input type="text" name="wp_fav_url" value="<?php echo $url; ?>" class="regular-text" id="wp_fav_url" />
            </td>
          </tr>
          <tr>
            <td class="wpfav-first"></td>
            <td>
		        <p class="submit" style="color:#999999"><input type="submit" value="Favorite" name="wp_fav_submit" /></p>
            </td>
          </tr>
        </table>
        </form>
      </div>
      
      <script type="text/javascript">
		var wp_fav = document.getElementById('wp_fav');
		wp_fav.onsubmit = function(){
			var title = document.getElementById('wp_fav_title').value;
			var url   = document.getElementById('wp_fav_url').value;
			if( !title ){
				alert(' TITLE couldn\'t be empty');	return false;
			}
			if( !url ){
				alert(' URL couldn\'t be empty');	return false;
			}
			if( !/^http(s)?:\/\/(.*)/i.test(url) ){
				alert('The URL illegal');	return false;
			}
		}
      </script>
<?php 
	}else{
?>
	<style type="text/css">
		.imgBtnTxt{width:152px; height: 32px; margin: 30px auto; line-height: 32px; text-align: center;}
		.imgBtnTxt a{display: block;line-height: 32px;color: #fff; background-color: #aaa;font-size: 16px;font-family: "微软雅黑";text-decoration: none;}
	</style>
	<div class="wrap">
		<?php if($message){?>
		<div id="message" class="updated fade"><p><?php echo $message; ?></p></div>
		<?php } ?>
		<h2>WP-favorite</h2>
		<p>使用WP-favorite，可以快速将您看到的网页收藏到自己博客，方便以后阅读，同时还可以将自己收藏的内容分享给网友。</p>
		<p class="imgBtnTxt"><a href="javascript:(function(){window.open('<?php echo get_option('siteurl');?>/wp-admin/options-general.php?page=wp_favorite&title='+encodeURIComponent(document.title)+'&url='+encodeURIComponent(location.href)+'&source=bookmark','_blank','width=450,height=400');})()" id="share" title="拖动加入收藏夹" style="cursor: move;">收藏到我的博客</a></p>
		<h3>如何收藏网页？</h3>
		<div class="step">
			<p><span>Step 1 </span>拖动上面的“收藏到我的博客”按钮，将其加入浏览器收藏夹中</p>
			<p><span>Step 2 </span>浏览到自己喜欢的网页时，打开收藏夹中“收藏到我的博客”书签</p>
			<p><span>Step 3 </span>进入博客后台，收藏网页！</p>
		</div>
		<h3>如何分享收藏的网页？</h3>
		<div class="step">
			<p><span>Step 1 </span>登录博客后台->页面->新建页面</p>
			<p><span>Step 2 </span>将“[wp_favorites limit="20" /]”以HTML代码方式黏贴到新建页面内容中。[注]<b>limit</b>为可变正整数</p>
			<p><span>Step 3 </span>保存页面，<a href="javascript:void(0);" title="请进入博客前台查看您为WP-favorite添加的页面">查看</a></p>
		</div>
	</div>
<?php 
	}
}
