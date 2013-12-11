<?php
/**
 * View for the AJAX star rating plugin.
 *
 * @author Michael Schneidt <michael.schneidt@arcor.de>
 * @copyright Copyright 2009, Michael Schneidt
 * @license http://www.opensource.org/licenses/mit-license.php
 * @link http://bakery.cakephp.org/articles/view/ajax-star-rating-plugin-1
 * @version 2.2
 */
?>
 
<?php
  // decision to enable or disable the rating
  $enable = ($this->Session->check(Configure::read('Rating.sessionUserId')) // logged in user or guest
               || (Configure::read('Rating.guest') && $this->Session->check('Rating.guest_id')))
             && !Configure::read('Rating.disable') // plugin enabled
             && (Configure::read('Rating.allowChange') // change is allowed or first rating
                 || (!Configure::read('Rating.allowChange') && $data['%RATING%'] == 0));

  // the images are initialized here as well as in js, to avoid flickering.
  echo $this->Rating->stars($model, $id, $data, $options, $enable);
  
  // format the statusText and write it back
  $text = $this->Rating->format(Configure::read('Rating.statusText'), $data);
  Configure::write('Rating.statusText', $text);
?>

<div id="<?php echo $model.'_rating_'.$options['name'].'_'.$id.'_text'; ?>" class="<?php echo !empty($text) ? 'rating-text' : 'rating-notext'; ?>">
  <?php
    echo $text;
  ?>
</div>

<?php
  // initialize the rating element
  if (!Configure::read('Rating.disable')) {
    
    echo $this->Html->scriptBlock("ratingInit('".$model.'_rating_'.$options['name'].'_'.$id."', "
                                           ."'".addslashes(json_encode($data))."'," 
                                           ."'".addslashes(json_encode($options))."'," 
                                           ."'".addslashes(json_encode(Configure::read('Rating')))."'," 
                                           .intval($enable).");", array( 'inline' => false));
    
  }
?>

<?php if (Configure::read('Rating.fallback')): ?>
<noscript>
  <div class="fallback">
  <?php
    if ($enable) {
      // show fallback form
      echo $this->Form->create('Rating', 
                         array('type' => 'get',
                               'url' => array('controller' => 'ratings', 'plugin' => 'rating', 'action' => 'save')));
      echo $this->Form->radio('value',
                        $this->Rating->options(), 
                        array('legend' => false,
                              'id' => $model.'_rating_'.$options['name'].'_'.$id,
                              'value' => $data['%RATING%']));
      echo $this->Form->hidden('model', array('value' => $model));
      echo $this->Form->hidden('rating', array('value' => $id));
      echo $this->Form->hidden('name', array('value' => $options['name']));
      echo $this->Form->hidden('config', array('value' => $options['config']));
      echo $this->Form->hidden('fallback', array('value' => true));
      echo $this->Form->submit(__('Vote', true),
                         array('div' => false,
                               'title' => __('Vote', true)));
      
      echo $this->Form->end();
    }
  ?>
  </div>
  
  <?php
      // get mouseover messages for showing
    $mouseOverMessages = Configure::read('Rating.mouseOverMessages');
  ?>
  
  <?php // show login message
        if (!$enable && Configure::read('Rating.showMouseOverMessages')
            && !empty($mouseOverMessages['login'])
            && !Configure::read('Rating.disable')
            && $data['%RATING%'] == 0): ?>
    <div id="<?php echo $model.'_rating_'.$options['name'].'_'.$id.'_text'; ?>" class="<?php echo !empty($text) ? 'rating-text' : 'rating-notext'; ?>">
      <?php
        echo $mouseOverMessages['login'];
      ?>
    </div>
  <?php endif; ?>
  
  <?php // show rated message
        if (!$enable && Configure::read('Rating.showMouseOverMessages')
            && !empty($mouseOverMessages['rated'])
            && $data['%RATING%'] > 0): ?>
    <div id="<?php echo $model.'_rating_'.$options['name'].'_'.$id.'_text'; ?>" class="<?php echo !empty($text) ? 'rating-text' : 'rating-notext'; ?>">
      <?php
        echo $mouseOverMessages['rated'];
      ?>
    </div>
  <?php endif; ?>
</noscript>
<?php endif; ?>

<?php
  // show flash message
  if (Configure::read('Rating.flash')) {
    CakeSession::flash('rating');
  }
?>