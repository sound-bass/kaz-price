<?php
/*
 * Plugin Name: KAZ Price
 * Plugin URI: http://kaz.km.ua
 * Description: upload price 
 * Version: 1.1.1
 * Author: Serhio
 * Author URI: https://github.com/sound-bass
 * License: GPLv2 or later
 */
if(preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) { die('You are not allowed to call this page directly.'); }

if (!class_exists('KazPrice')) {
 class KazPrice {
 
	## Хранение внутренних данных
	public $data = array();
	
	## Конструктор объекта
	## Инициализация основных переменных
	function KazPrice()
	{
		global $wpdb;
		
		## Объявляем константу инициализации нашего плагина
		DEFINE('KazPrice', true);
		
		## Название файла нашего плагина 
		$this->plugin_name = plugin_basename(__FILE__);
		
		## URL адресс для нашего плагина
		$this->plugin_url = trailingslashit(WP_PLUGIN_URL.'/'.dirname(plugin_basename(__FILE__)));
		
		## Таблица для хранения наших отзывов
		## обязательно должна быть глобально объявлена перменная $wpdb
		$this->tbl_adv_reviews   = $wpdb->prefix . 'kaz_price';
		
		## Функция которая исполняется при активации плагина
		register_activation_hook( $this->plugin_name, array(&$this, 'activate') );
		
		## Функция которая исполняется при деактивации плагина
		register_deactivation_hook( $this->plugin_name, array(&$this, 'deactivate') );
		
		##  Функция которая исполняется удалении плагина
		register_uninstall_hook( $this->plugin_name, array(&$this, 'uninstall') );
		
		// Если мы в адм. интерфейсе
		if ( is_admin() ) {
			
			// Добавляем стили и скрипты
			add_action('wp_print_scripts', array(&$this, 'admin_load_scripts'));
			add_action('wp_print_styles', array(&$this, 'admin_load_styles'));
			
			// Добавляем меню для плагина
			add_action( 'admin_menu', array(&$this, 'admin_generate_menu') );
			
		} else {
		    // Добавляем стили и скрипты
			add_action('wp_print_scripts', array(&$this, 'site_load_scripts'));
			add_action('wp_print_styles', array(&$this, 'site_load_styles'));
			
			add_shortcode('show_reviews', array (&$this, 'site_show_reviews'));
		}
	}
	
	/**
	 * Загрузка необходимых скриптов для страницы управления 
	 * в панели администрирования
	 */
	function admin_load_scripts()
	{
		// Региестрируем скрипты
		wp_register_script('advReviewsAdminJs', $this->plugin_url . 'js/admin-scripts.js' );
		wp_register_script('jquery', $this->plugin_url . 'js/jquery-1.4.2.min.js' );
		
		// Добавляем скрипты на страницу
		wp_enqueue_script('advReviewsAdminJs');
		wp_enqueue_script('jquery');
	}
	
	/**
	 * Загрузка необходимых стилей для страницы управления 
	 * в панели администрирования
	 */
	function admin_load_styles()
	{	
		// Регистрируем стили 
		wp_register_style('advReviewsAdminCss', $this->plugin_url . 'css/admin-style.css' );
		// Добавляем стили
        wp_enqueue_style('advReviewsAdminCss');
	}
	
	/**
 	 * Генерируем меню
	 */
	function admin_generate_menu()
	{
		// Добавляем основной раздел меню
		add_menu_page('Добро пожаловать в модуль управления отзывами', 'Отзывы', 'manage_options', 'edit-reviews', array(&$this, 'admin_edit_reviews'));
		// Добавляем дополнительный раздел
		add_submenu_page( 'edit-reviews', 'Управление содержимом', 'О плагине', 'manage_options', 'plugin_info', array(&$this,'admin_plugin_info'));
	}
	
	/**
	 * Выводим список отзывов для редактирования
	 */
	public function admin_edit_reviews()
	{
		global $wpdb;
		
		$action = isset($_GET['action']) ? $_GET['action'] : null ;
		
		switch ($action) {
		
			case 'edit':
				// Получаем данные из БД
				$this->data['review'] 	= $wpdb->get_row("SELECT * FROM `" . $this->tbl_adv_reviews . "` WHERE `ID`= ". (int)$_GET['id'], ARRAY_A);
				
				// Подключаем страницу для отображения результатов 
				include_once('edit_review.php');
			break;
			
			case 'submit':
				$inputData = array(
					'review_title' 	  	  => strip_tags($_POST['review_title']),
					'review_text' 		  => strip_tags($_POST['review_text']),
					'review_user_name' 	  => strip_tags($_POST['review_user_name']),
					'review_user_email'   => strip_tags($_POST['review_user_email']),
				);
			
				$editId=intval($_POST['id']);
			
				if ($editId == 0) return false;
			
				// Обновляем существующую запись
				$wpdb->update( $this->tbl_adv_reviews, $inputData, array( 'ID' => $editId ));
				
				// Показываем список отзывов
				$this->admin_show_reviews();
			break;
			
			case 'delete':
			
				// Удаляем существующую запись
				$wpdb->query("DELETE FROM `".$this->tbl_adv_reviews."` WHERE `ID` = '". (int)$_GET['id'] ."'");
				
				// Показываем список отзывов
				$this->admin_show_reviews();
			break;
			
			default:
				$this->admin_show_reviews();
		}
		
	}
	
	/**
	 * Функция для отображения списка отзывов в адм. панели
	 */
	private function admin_show_reviews()
	{
		global $wpdb;
		
		// Получаем данные из БД
		$this->data['reviews'] 	 = $wpdb->get_results("SELECT * FROM `" . $this->tbl_adv_reviews . "`", ARRAY_A);
		
		// Подключаем страницу для отображения результатов 
		include_once('view_reviews.php');
	}
	
	/**
	 * Показываем статическую страницу
	 */
	public function admin_plugin_info()
	{
		include_once('plugin_info.php');
	}
	
	function site_load_scripts()
	{
		wp_register_script('jquery', $this->plugin_url . 'js/jquery-1.4.2.min.js' );
		wp_register_script('advReviewsJs', $this->plugin_url . 'js/site-scripts.js' );
		wp_enqueue_script('jquery');
		wp_enqueue_script('advReviewsJs');
	}

	function site_load_styles()
	{
		wp_register_style('advReviewsCss', $this->plugin_url . 'css/site-style.css' );
		wp_enqueue_style('advReviewsCss');
	}
	
	/**
	 * Список отзывов на сайте
	 */
	public function site_show_reviews($atts, $content=null)
	{
		global $wpdb;
		
		if (isset($_POST['action']) && $_POST['action'] == 'add-review') {
			$this->add_user_review();
		}
		
		// Выбираем все отзывы из Базы Данных
		$this->data['reviews'] = $wpdb->get_results("SELECT * FROM `" . $this->tbl_adv_reviews . "`", ARRAY_A);
		
		## Включаем буферизацию вывода
		ob_start ();
		include_once('site_reviews.php');
		## Получаем данные
		$output = ob_get_contents ();
		## Отключаем буферизацию
		ob_end_clean ();
		
		return $output;
	}
	
	private function add_user_review() 
	{
		global $wpdb;
		
		$inputData = array(
			'review_title' 	  	  => strip_tags($_POST['review_title']),
			'review_text' 		  => strip_tags($_POST['review_text']),
			'review_user_name' 	  => strip_tags($_POST['review_user_name']),
			'review_user_email'   => strip_tags($_POST['review_user_email']),
		);
		
		// Добавляем новый отзыв на сайт	
		$wpdb->insert( $this->tbl_adv_reviews, $inputData );
	}
	
	
	/**
	 * Активация плагина
	 */
	function activate() 
	{
		global $wpdb;
		
		require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
		
		$table	= $this->tbl_adv_reviews;
		
		## Определение версии mysql
		if ( version_compare(mysql_get_server_info(), '4.1.0', '>=') ) {
			if ( ! empty($wpdb->charset) )
				$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
			if ( ! empty($wpdb->collate) )
				$charset_collate .= " COLLATE $wpdb->collate";
		}
		
		## Структура нашей таблицы для отзывов
		$sql_table_adv_reviews = "
				CREATE TABLE `".$wpdb->prefix."adv_reviews` (
					`ID` INT(10) UNSIGNED NULL AUTO_INCREMENT,
					`review_title` VARCHAR(255) NOT NULL DEFAULT '0',
					`review_text` TEXT NOT NULL,
					`review_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
					`review_user_name` VARCHAR(200) NULL,
					`review_user_email` VARCHAR(200) NULL,
					PRIMARY KEY (`ID`)
				)".$charset_collate.";";
		
		## Проверка на существование таблицы	
		if ( $wpdb->get_var("show tables like '".$table."'") != $table ) {
			dbDelta($sql_table_adv_reviews);
		}
		
	}
	
	function deactivate() 
	{
		return true;
	}
	
	/**
	 * Удаление плагина
	 */
	function uninstall() 
	{
		global $wpdb;
		
		$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}adv_reviews");
	}

 }
}

global $reviews;
$reviews = new KazPrice();
?>