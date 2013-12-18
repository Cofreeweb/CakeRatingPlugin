<?php

class RatingsSchema extends CakeSchema {

	var $name = 'Ratings';

	function before($event = array()) {
		return true;
	}

	function after($event = array()) {
	}
  
	var $ratings = array(
			'id' =>                   array('type'=>'integer', 'null' => false, 'default' => NULL, 'length' => 10, 'key' => 'primary'),
			'user_id' =>              array('type'=>'string', 'length' => 36, 'null' => false),
			'model_id' =>             array('type'=>'string', 'length' => 36, 'null' => false),
			'model' =>             array('type'=>'string', 'length' => 100, 'null' => false),
			'rating' =>               array('type'=>'integer', 'length' => 2, 'null' => false, 'default' => '0'),
			'name' =>                 array('type'=>'string', 'length' => 100, 'null' => true),
			'created' =>              array('type'=>'datetime', 'null' => true),
      'modified' =>             array('type'=>'datetime', 'null' => true),
			'indexes' =>              array('PRIMARY' => array('column' => 'id', 'unique' => 1))
		);
		
}
?>