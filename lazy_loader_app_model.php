<?php
/*
 * CakePHP Lazy Loader Plugin
 * Copyright (c) 2009 Matt Curry
 * http://www.pseudocoder.com/archives/2009/04/27/github-updates
 *
 * This is my second attempt at this.
 * The code posted by jose_zap (José Lorenzo - http://joselorenzo.com.ve/)
 * on bin.cakephp.org provided inspiration for this revised version
 * http://bin.cakephp.org/saved/39855
 *
 * @author      Matt Curry <matt@pseudocoder.com>
 * @license     MIT
 *
 */
if (!class_exists('AppModel')) {
	App::import('Model','AppModel');
}
class LazyLoaderAppModel extends AppModel {
  var $__originalClassName = array();

  function __isset($name) {
    $className = false;

    foreach ($this->__associations as $type) {
      if (array_key_exists($name, $this-> {$type})) {
        $className = $this->__originalClassName[$this-> {$type}[$name]['className']];
        break;
      }
      if($type == 'hasAndBelongsToMany') {
        $withs = Set::extract('/with', array_values($this-> {$type}));
        if(in_array($name, $withs)) {
          $className = isset($this->__originalClassName[$name]) ? $this->__originalClassName[$name] : $name;
          break;
        }
      }
    }

    if($className) {
      parent::__constructLinkedModel($name, $className);
      parent::__generateAssociation($type);
      return $this-> {$name};
    }

    return false;
  }

  function __get($name) {
    if (isset($this-> {$name})) {
      return $this-> {$name};
    }

    return false;
  }

  function __constructLinkedModel($assoc, $className = null) {
    foreach ($this->__associations as $type) {
      if (isset($this-> {$type}[$assoc])) {
        return;
      }
      if($type == 'hasAndBelongsToMany') {
        $withs = Set::extract('/with', array_values($this-> {$type}));
        if(in_array($assoc, $withs)) {
          return;
        }
      }
    }

    return parent::__constructLinkedModel($assoc, $className);
  }

  function resetAssociations() {
	if (!empty($this->__backAssociation)) {
		foreach ($this->__associations as $type) {
			if (isset($this->__backAssociation[$type])) {
				$this->{$type} = $this->__backAssociation[$type];
			}
		}
		$this->__backAssociation = array();
	}

	foreach ($this->__associations as $type) {
		foreach ($this->{$type} as $key => $name) {
			if (ClassRegistry::isKeySet($key) && !empty($this->{$key}->__backAssociation)) {
				$this->{$key}->resetAssociations();
			}
		}
	}

	$this->__backAssociation = array();
	return true;
  }

  function __createLinks() {
	foreach ($this->__associations as $type) {
		if (!empty($this->{$type})) {
			foreach ($this->{$type} as $assoc => $value) {
				$className = $assoc;
				$plugin = null;
				if (is_numeric($assoc)) {
					$className = $value;
					$value = array();
					if (strpos($assoc, '.') !== false) {
						list($plugin, $className) = explode('.', $className);
						$plugin = $plugin . '.';
					}
				}

				if (isset($value['className']) && !empty($value['className'])) {
					$className = $value['className'];
					if (strpos($className, '.') !== false) {
						list($plugin, $className) = explode('.', $className);
						$plugin = $plugin . '.';
					}
				}

				if (is_array($this->{$type}[$assoc]) && !empty($this->{$type}[$assoc]['with'])) {
					$joinClass = $this->{$type}[$assoc]['with'];
					if (is_array($joinClass)) {
						$joinClass = key($joinClass);
					}
					if (strpos($joinClass, '.') !== false) {
						list($plugin, $joinClass) = explode('.', $joinClass);
						$plugin = $plugin . '.';
					}

					if (empty($this->__originalClassName[$joinClass])) {
						$this->__originalClassName[$joinClass] = $plugin.$joinClass;
					}
				}

				if (empty($this->__originalClassName[$className])) {
						$this->__originalClassName[$className] = $plugin.$className;
				}
			}
		}
	 }
	 parent::__createLinks();
  }
}
?>