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

class LazyLoaderAppModel extends AppModel {

  /**
  * Stores the original string as set in 'className' as association options befor it gets changed
  */
  var $__originalClassName = array();

  function __isset($name) {
    $className = false;

    foreach ($this->__associations as $type) {
      if (array_key_exists($name, $this-> {$type})) {
        $className = $this->__originalClassName[$this-> {$type}[$name]['className']];
        break;
      }
      if($type == 'hasAndBelongsToMany') {
		foreach ($this->{$type} as $k => $relation) {
			if (!empty($relation['with'])) {

				if (isset($this->__originalClassName[$name])) {
					$className = $this->__originalClassName[$name];
				} elseif ($relation['with'] === $name) {
					$className = $name;
				}

				if ($className) {
					$assocKey = $k;
					break(2);
				}
			}
		}
      }
    }

    if($className) {
      parent::__constructLinkedModel($name, $className);

      if (!empty($assocKey)) {
		$this->hasAndBelongsToMany[$assocKey]['joinTable'] = $this->{$name}->table;
		if (count($this->{$name}->schema()) <= 2 && $this->{$name}->primaryKey !== false) {
			$this->{$name}->primaryKey = $this->hasAndBelongsToMany[$assocKey]['foreignKey'];
		}
      }

      return $this->{$name};
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
		foreach ($this->{$type} as $relation) {
			if (!empty($relation['with']) && $relation['with'] === $assoc) {
				return;
			}
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
			if (property_exists($this, $key) && !empty($this->{$key}->__backAssociation)) {
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

				if (!empty($value['className'])) {
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

/**
 * Build an array-based association from string.
 *
 * @param string $type 'belongsTo', 'hasOne', 'hasMany', 'hasAndBelongsToMany'
 * @return void
 * @access private
 */
	function __generateAssociation($type) {
		foreach ($this->{$type} as $assocKey => $assocData) {
			$class = $assocKey;
			$dynamicWith = false;

			foreach ($this->__associationKeys[$type] as $key) {

				if (!isset($this->{$type}[$assocKey][$key]) || $this->{$type}[$assocKey][$key] === null) {
					$data = '';

					switch ($key) {
						case 'fields':
							$data = '';
						break;

						case 'foreignKey':
							$data = (($type == 'belongsTo') ? Inflector::underscore($assocKey) : Inflector::singularize($this->table)) . '_id';
						break;

						case 'associationForeignKey':
							$data = Inflector::singularize($this->{$class}->table) . '_id';
						break;

						case 'with':
							$data = Inflector::camelize(Inflector::singularize($this->{$type}[$assocKey]['joinTable']));
							$dynamicWith = true;
						break;

						case 'joinTable':
							$tables = array($this->table, $this->{$class}->table);
							sort ($tables);
							$data = $tables[0] . '_' . $tables[1];
						break;

						case 'className':
							$data = $class;
						break;

						case 'unique':
							$data = true;
						break;
					}
					$this->{$type}[$assocKey][$key] = $data;
				}
			}

			if (!empty($this->{$type}[$assocKey]['with'])) {
				$joinClass = $this->{$type}[$assocKey]['with'];
				if (is_array($joinClass)) {
					$joinClass = key($joinClass);
				}

				$plugin = null;
				if (strpos($joinClass, '.') !== false) {
					list($plugin, $joinClass) = explode('.', $joinClass);
					$plugin .= '.';
					$this->{$type}[$assocKey]['with'] = $joinClass;
				}

				if (!ClassRegistry::isKeySet($joinClass) && $dynamicWith === true) {
					$this->{$joinClass} = new AppModel(array(
						'name' => $joinClass,
						'table' => $this->{$type}[$assocKey]['joinTable'],
						'ds' => $this->useDbConfig
					));
				}
			}
		}
	}
}