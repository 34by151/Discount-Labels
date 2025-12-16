<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Inversion of control for plugin.
 *
 * @since      1.0.0
 * @package    WC_Conditions
 * @subpackage WC_Conditions/includes
 * @author     Taher Atashbar <taher.atashbar@gmail.com>
 */
class WCCS_Service_Manager {

	/**
	 * Container for plugin global objects.
	 *
	 * @since 1.0.0
	 * @var   array
	 */
	private $container = array();

	/**
	 * Maginc get method.
	 *
	 * @since  1.0.0
	 *
	 * @return mixed
	 */
	public function __get( $key ) {
		return $this->get( $key );
	}

	/**
	 * Magic set method.
	 *
	 * @since  1.0.0
	 *
	 * @param  string $key
	 * @param  mixed  $value
	 *
	 * @return mixed
	 */
	public function __set( $key, $value ) {
		return $this->set( $key, $value );
	}

	/**
	 * Getting value of key from container.
	 *
	 * @since  1.0.0
	 *
	 * @param  string  $key
	 * @param  array   $arguments
	 * @param  mixed   $default
	 * @param  boolean $create     // If the gvien key is class and already does not exists in the container then create new one.
	 *
	 * @return mixed
	 */
	public function get( $key, array $arguments = array(), $default = null, $create = true ) {
		if ( isset( $this->container[ $key ] ) ) {
			return $this->container[ $key ];
		} elseif ( $create && false !== strpos( $key, 'WCCS' ) && class_exists( $key ) ) {
			if ( empty( $arguments ) ) {
				$class = new $key;
				$this->set( $key, $class );
				return $class;
			}

			$reflector = new ReflectionClass( $key );
			$class     = $reflector->newInstanceArgs( $arguments );
			$this->set( $key, $class );
			return $class;
		}

		return $default;
	}

	/**
	 * Binding value to key in container.
	 *
	 * @since  1.0.0
	 *
	 * @param  string $key
	 * @param  mixed $value
	 *
	 * @return mixed
	 */
	public function set( $key, $value ) {
		return $this->container[ $key ] = $value;
	}

}
