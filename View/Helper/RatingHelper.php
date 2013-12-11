<?php
/**
 * Helper for the AJAX star rating plugin.
 *
 * @author Michael Schneidt <michael.schneidt@arcor.de>
 * @copyright Copyright 2009, Michael Schneidt
 * @license http://www.opensource.org/licenses/mit-license.php
 * @link http://bakery.cakephp.org/articles/view/ajax-star-rating-plugin-1
 * @version 2.2
 */
class RatingHelper extends AppHelper 
{
  public $helpers = array('Html', 'Form', 'Session');
  
  
  private $_models = array();
  
/**
 * Guess the location for a model based on its name and tries to create a new instance
 * or get an already created instance of the model
 *
 * @param string $model
 * @return Model model instance
 */
	protected function _getModel($model) {
		$object = null;
		if( !$model || $model === 'Model') {
			return $object;
		}

		if( array_key_exists($model, $this->_models)) {
			return $this->_models[$model];
		}

		if( ClassRegistry::isKeySet($model)) {
			$object = ClassRegistry::getObject($model);
		} elseif( isset($this->request->params['models'][$model])) {
			$plugin = $this->request->params['models'][$model]['plugin'];
			$plugin .=( $plugin) ? '.' : null;
			$object = ClassRegistry::init(array(
				'class' => $plugin . $this->request->params['models'][$model]['className'],
				'alias' => $model
			));
		} elseif( ClassRegistry::isKeySet($this->defaultModel)) {
			$defaultObject = ClassRegistry::getObject($this->defaultModel);
			if( in_array($model, array_keys($defaultObject->getAssociated()), true) && isset($defaultObject->{$model})) {
				$object = $defaultObject->{$model};
			}
		} else {
			$object = ClassRegistry::init($model, true);
		}

		$this->_models[$model] = $object;
		if( !$object) {
			return null;
		}

		$this->fieldset[$model] = array('fields' => null, 'key' => $object->primaryKey, 'validates' => null);
		return $object;
	}
	
  /**
   * Load a config file.
   * 
   * @param $config Name of the config file
   */
  private function __loadConfig($config) {
    if( !Configure::read( 'Rating'))
    {
      if( Configure::load('Rating.'.$config) === false) {
        echo 'Error: The '.$config.'.php was not found in your app/config directory. Please copy it from rating/config/plugin.rating.php';
        exit;
      }
    }
  }
  
  /**
   * Setup the guest id in session and cookie.
   */
  private function __setupGuest() {
    if( !$this->Session->check('Rating.guest_id')) {
      App::import('Core', 'String');
      $uuid = String::uuid();

      CakeSession::write('Rating.guest_id', $uuid);
    } else if( Configure::read('Rating.guest')) {
      // $this->Session->write('Rating.guest_id', $uuid);
    }
  }
  
  
  public function view($model = '', $id = 0, $options = '') {
    $userRating = null;
    $avgRating = null;
    $votes = null;
    $modelInstance = ClassRegistry::init($model);
    $optionsData = json_decode(base64_decode($options), true);
    
    $name = $optionsData['name'];
    $config = $optionsData['config'];    
    
    // load the config file
    $this->__loadConfig($config);
    
    // setup guest access
    if( Configure::read('Rating.guest') 
        && !$this->Session->check(Configure::read('Rating.sessionUserId'))) {
      $this->__setupGuest();
    }
    
    // check if user id exists in session
    if( Configure::read('Rating.showHelp') 
        && !Configure::read('Rating.guest') 
        &&( !$this->Session->check(Configure::read('Rating.sessionUserId')) 
            || !$this->Session->read(Configure::read('Rating.sessionUserId')) > 0)) {
      echo 'Warning: No valid user id was found at "'.Configure::read('Rating.sessionUserId').'" in the session.';
    }
    
    // check if model id exists
    $modelInstance->id = $id;
    
    if( Configure::read('Rating.showHelp') && !$modelInstance->exists(true)) {
      echo 'Error: The model_id "'.$id.'" of "'.$model.'" does not exist.';
    }

    // choose between user id and guest id
    if( !$this->Session->read(Configure::read('Rating.sessionUserId')) 
        &&( Configure::read('Rating.guest') && $this->Session->read('Rating.guest_id'))) {
      $userId = $this->Session->read('Rating.guest_id');
    } else {
      $userId = $this->Session->read(Configure::read('Rating.sessionUserId'));
    }

    if( !empty($userId)) {
      $userRating = $this->_getModel( 'Rating.Rating')->field('rating',
                                         array('model' => $model, 
                                               'model_id' => $id, 
                                               'user_id' => $userId,
                                               'name' => $name));
    }

    if( empty($userRating)) {
      $userRating = 0;
    }
    
    // retrieve rating values from model or calculate them
    if( Configure::read('Rating.saveToModel')) {
      if( Configure::read('Rating.showHelp') 
          && !$modelInstance->hasField(Configure::read('Rating.modelAverageField'))) {
        echo 'Error: The average field "'.Configure::read('Rating.modelAverageField').'" in the model "'.$model.'" does not exist.';
      }
      
      if( Configure::read('Rating.showHelp') 
          && !$modelInstance->hasField(Configure::read('Rating.modelVotesField'))) {
        echo 'Error: The votes field "'.Configure::read('Rating.modelVotesField').'" in the model "'.$model.'" does not exist.';
      }
      
      $values = $modelInstance->find( 'first', array(
          'conditions' => array(
              $modelInstance->alias .'.'. $modelInstance->primaryKey => $id
          ),
          'fields' => array(
              Configure::read('Rating.modelAverageField'),
              Configure::read('Rating.modelVotesField')
          ),
          'recursive' => -1
      ));

      
      $avgRating = $values[$modelInstance->name][Configure::read('Rating.modelAverageField')];
      $votes = $values[$modelInstance->name][Configure::read('Rating.modelVotesField')];
    } else {
      $values = $this->_getModel( 'Rating.Rating')->find(array('model' => $model,
                                          'model_id' => $id,
                                          'name' => $name),
                                    array('AVG(Rating.rating)', 'COUNT(*)'));
      
      $avgRating = round($values[0]['AVG(`Rating`.`rating`)'], 1);
      $votes = $values[0]['COUNT(*)'];
    }
    
    if( empty($votes)) {
      $votes = 0;
    }
    
    if( $avgRating && !strpos($avgRating, '.')) {
      $avgRating = $avgRating.'.0';
    } else if( !$avgRating) {
      $avgRating = '0.0';
    }
    
    $return = $this->_View->element( 'Rating.view', array(
        'id' => $id,
        'model' => $model,
        'options' => $optionsData,
        'data' => array(
            '%VOTES%' => $votes.' '.__n('', '', $votes, true),
            '%RATING%' => $userRating, 
            '%AVG%' => $avgRating,
            '%MAX%' => Configure::read('Rating.maxRating')
        )
    ));
    
    return $return;
  }
  
  
  /**
   * Creates the stars for a rating.
   *
   * @param string $model Model name
   * @param integer $id Model id
   * @param array $data Rating data
   * @param array $options Options
   * @param boolean $enable Enable element
   * @return Stars as HTML images
   */
  function stars($model, $id, $data, $options, $enable)
  {
    $output = '';
    $star_class = Configure::read('Rating.statEmptyClass');
    
    if( Configure::read('Rating.showUserRatingStars')) 
    {
      $stars = $data['%RATING%'];
    } 
    else 
    {
      $stars = $data['%AVG%'];
    }
    
    for( $i = 1; $i <= $data['%MAX%']; $i++) 
    {
      if( $i <= floor($stars)) 
      {
        $star_class = Configure::read('Rating.statFullClass');
      } 
      else if( $i == floor($stars) + 1 && preg_match('/[0-9]\.[5-9]/', $stars)) 
      {
        $star_class = Configure::read('Rating.statHalfClass');
      } 
      else 
      {
        $star_class = Configure::read('Rating.statEmptyClass');
      }
      
      if( Configure::read('Rating.showUserRatingMark') && $i <= $data['%RATING%']) 
      {
        $class = 'rating-user';
      } 
      else 
      {
        $class = 'rating';
      }
      
      if( !$enable) 
      {
        $class .= '-disabled';
      }
      
      $icon = '<i id="'. $model.'_rating_'.$options['name'].'_'.$id.'_'.$i .'" class="'. $star_class .'"></i>';

      if( Configure::read('Rating.fallback')) {
        $output .= $this->Form->label( $model.'.rating', $icon, array(
            'for' => $model.'Rating'.ucfirst($options['name']).$id.$i
        ));
      } else {
        $output .= $icon;
      }
    }

    return $output;
  }
  
  /**
   * Formats a text in replacing data wildcards.
   *
   * @param string $text
   * @param array $data
   * @return Formatted text
   */
  function format($text, $data) 
  {
    foreach( $data as $wildcard => $value) {
      $text = str_replace($wildcard, $value, $text);
    }
    
    // fix lost blanks in js( excluding blanks between html tags)
    $text = preg_replace('/(?!(?:[^<]+>|[^>]+<\/(.*)>))( )/', '&nbsp;', $text);
    
    return $text;
  }
  
  /**
   * Creates options for fallback radio buttons.
   * 
   * @return Radio options
   */
  function options() {
    $options = array();
    
    if( Configure::read('Rating.showMouseOverMessages')) {
      $options = Configure::read('Rating.mouseOverMessages');
      unset($options['login'], $options['rated'], $options['delete']);
    } else {
      $options = range(0, Configure::read('Rating.maxRating'));
      unset($options[0]);
    }
    
    return $options;
  }
}
?>