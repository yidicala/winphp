<?php

include_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'Image_Driver.php');

class Image {

	// Master Dimension
	const NONE = 1;
	const AUTO = 2;
	const HEIGHT = 3;
	const WIDTH = 4;
	// Flip Directions
	const HORIZONTAL = 5;
	const VERTICAL = 6;

	// Allowed image types
	public static $allowed_types = array
	(
		IMAGETYPE_GIF => 'gif',
		IMAGETYPE_JPEG => 'jpg',
		IMAGETYPE_PNG => 'png',
		IMAGETYPE_TIFF_II => 'tiff',
		IMAGETYPE_TIFF_MM => 'tiff',
	);

	// Driver instance
	protected $driver;

	// Driver actions
	protected $actions = array();

	// Reference to the current image filename
	protected $image = '';

	/**
	 * Creates a new Image instance and returns it.
	 *
	 * @param   string   filename of image
	 * @param   array    non-default configurations
	 * @return  object
	 */
	public static function factory($image, $config = NULL)
	{
		return new Image($image, $config);
	}

	/**
	 * Creates a new image editor instance.
	 *
	 * @throws  Kohana_Exception
	 * @param   string   filename of image
	 * @param   array    non-default configurations
	 * @return  void
	 */
	public function __construct($image, $config = NULL)
	{
		static $check;

		// Make the check exactly once
		($check === NULL) and $check = function_exists('getimagesize');

		if ($check === FALSE)
			throw new Exception('image getimagesize missing');

		// Check to make sure the image exists
		if ( ! is_file($image))
			throw new Exception('image file not found');

		// Disable error reporting, to prevent PHP warnings
		$ER = error_reporting(0);

		// Fetch the image size and mime type
		$image_info = getimagesize($image);

		// Turn on error reporting again
		error_reporting($ER);

		// Make sure that the image is readable and valid
		if ( ! is_array($image_info) OR count($image_info) < 3)
			throw new Exception('image file unreadable');

		// Check to make sure the image type is allowed
		if ( ! isset(Image::$allowed_types[$image_info[2]]))
			throw new Exception('image type not allowed');

		// Image has been validated, load it
		$this->image = array
		(
			'file' => str_replace('\\', '/', realpath($image)),
			'width' => $image_info[0],
			'height' => $image_info[1],
			'type' => $image_info[2],
			'ext' => Image::$allowed_types[$image_info[2]],
			'mime' => $image_info['mime']
		);

		// Load configuration
        if ($config === null){
            $this->config = array(
                'driver'=>'GD',
                'params'=>array(),
            );
        }
        else{
            $this->config = $config;
        }

		// Set driver class name
		$driver = 'Image_'.ucfirst($this->config['driver']).'_Driver';

        // Load the driver
        include_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'drivers'.DIRECTORY_SEPARATOR.$driver.'.php');

		// Initialize the driver
		$this->driver = new $driver($this->config['params']);

		// Validate the driver
		if ( ! ($this->driver instanceof Image_Driver))
			throw new Exception('image driver must be implement Image_Driver class');
	}

	/**
	 * Handles retrieval of pre-save image properties
	 *
	 * @param   string  property name
	 * @return  mixed
	 */
	public function __get($property)
	{
		if (isset($this->image[$property]))
		{
			return $this->image[$property];
		}
		else
		{
			throw new Exception('invalid property');
		}
	}

	/**
	 * Resize an image to a specific width and height. By default, Kohana will
	 * maintain the aspect ratio using the width as the master dimension. If you
	 * wish to use height as master dim, set $image->master_dim = Image::HEIGHT
	 * This method is chainable.
	 *
	 * @throws  Kohana_Exception
	 * @param   integer  width
	 * @param   integer  height
	 * @param   integer  one of: Image::NONE, Image::AUTO, Image::WIDTH, Image::HEIGHT
	 * @return  object
	 */
	public function resize($width, $height, $master = NULL)
	{
		if ( ! $this->valid_size('width', $width))
			throw new Exception('image invalid width');

		if ( ! $this->valid_size('height', $height))
			throw new Exception('image invalid height');

		if (empty($width) AND empty($height))
			throw new Exception('image invalid dimensions');

		if ($master === NULL)
		{
			// Maintain the aspect ratio by default
			$master = Image::AUTO;
		}
		elseif ( ! $this->valid_size('master', $master))
			throw new Exception('image invalid master');

		$this->actions['resize'] = array
		(
			'width'  => $width,
			'height' => $height,
			'master' => $master,
		);

		return $this;
	}

    /**
	 * Crop an image to a specific width and height. You may also set the top
	 * and left offset.
	 * This method is chainable.
	 *
	 * @throws  Kohana_Exception
	 * @param   integer  width
	 * @param   integer  height
	 * @param   integer  top offset, pixel value or one of: top, center, bottom
	 * @param   integer  left offset, pixel value or one of: left, center, right
	 * @return  object
	 */
	public function crop($width, $height, $top = 'center', $left = 'center')
	{
		if ( ! $this->valid_size('width', $width))
			throw new Exception('image invalid width: '.$width);

		if ( ! $this->valid_size('height', $height))
			throw new Exception('image invalid height: '.$height);

		if ( ! $this->valid_size('top', $top))
			throw new Exception('image invalid top: '.$top);

		if ( ! $this->valid_size('left', $left))
			throw new Exception('image invalid left: '.$left);

		if (empty($width) AND empty($height))
			throw new Exception('image invalid dimensions');

		$this->actions['crop'] = array
		(
			'width'  => $width,
			'height' => $height,
			'top'    => $top,
			'left'   => $left,
		);

		return $this;
	}

    /**
	 * Allows rotation of an image by 180 degrees clockwise or counter clockwise.
	 *
	 * @param   integer  degrees
	 * @return  object
	 */
	public function rotate($degrees)
	{
		$degrees = (int) $degrees;

		if ($degrees > 180)
		{
			do
			{
				// Keep subtracting full circles until the degrees have normalized
				$degrees -= 360;
			}
			while($degrees > 180);
		}

		if ($degrees < -180)
		{
			do
			{
				// Keep adding full circles until the degrees have normalized
				$degrees += 360;
			}
			while($degrees < -180);
		}

		$this->actions['rotate'] = $degrees;

		return $this;
	}

    /**
	 * Flip an image horizontally or vertically.
	 *
	 * @throws  Kohana_Exception
	 * @param   integer  direction
	 * @return  object
	 */
	public function flip($direction)
	{
		if ($direction !== self::HORIZONTAL AND $direction !== self::VERTICAL)
			throw new Exception('image invalid flip');

		$this->actions['flip'] = $direction;

		return $this;
	}

    /**
	 * Change the quality of an image.
	 *
	 * @param   integer  quality as a percentage
	 * @return  object
	 */
	public function quality($amount)
	{
		$this->actions['quality'] = max(1, min($amount, 100));

		return $this;
	}

	/**
	 * Sharpen an image.
	 *
	 * @param   integer  amount to sharpen, usually ~20 is ideal
	 * @return  object
	 */
	public function sharpen($amount)
	{
		$this->actions['sharpen'] = max(1, min($amount, 100));

		return $this;
	}

	public function watermark($watermark, $offset_x, $offset_y, $opacity = 100){
		
		if(!is_file($watermark)){
			throw new Exception('watermark invalid file');
		}

		if (substr($offset_x, -1) === '%'){
			$offset_x = round($this->image['height'] * (substr($offset_x, 0, -1) / 100));
		}
		
		if (substr($offset_y, -1) === '%'){
			$offset_y = round($this->image['width'] * (substr($offset_y, 0, -1) / 100));
		}
		
		$opacity = min(max($opacity, 1), 100);
		
		$this->actions['watermark'] = array(
			'watermark' => $watermark,
			'offset_x' => $offset_x,
			'offset_y' => $offset_y,
			'opacity' => $opacity,
		);

		return $this;
	}

	public function blur($quality, $sigma = '')
	{
		if (empty($quality))
			throw new Exception('quality invalid dimensions');

		$this->actions['blur'] = array
		(
			'quality'  => $quality,
			'sigma' => $sigma
		);

		return $this;
	}

	public function exec($cmdline){
		$actions = array(
			'exec' => $cmdline
		);

		$status = $this->driver->execute($actions);		

		return $status;
	}

	/**
	 * Save the image to a new image or overwrite this image.
	 *
	 * @throws  Kohana_Exception
	 * @param   string   new image filename
	 * @param   integer  permissions for new image
	 * @param   boolean  keep or discard image process actions
	 * @return  object
	 */
	public function save($new_image = FALSE, $chmod = 0644, $keep_actions = FALSE)
	{
		// If no new image is defined, use the current image
		empty($new_image) and $new_image = $this->image['file'];

		// Separate the directory and filename
		$dir  = pathinfo($new_image, PATHINFO_DIRNAME);
		$file = pathinfo($new_image, PATHINFO_BASENAME);

		// Normalize the path
		$dir = str_replace('\\', '/', realpath($dir)).'/';

		if ( ! is_writable($dir))
			throw new Exception('image directory unwritable');

		if ($status = $this->driver->process($this->image, $this->actions, $dir, $file))
		{
			if ($chmod !== FALSE)
			{
				// Set permissions
				chmod($new_image, $chmod);
			}
		}
		
		// Reset actions. Subsequent save() or render() will not apply previous actions.
		if ($keep_actions === FALSE)
			$this->actions = array();
		
		return $status;
	}
	
	/** 
	 * Output the image to the browser. 
	 * 
	 * @param   boolean  keep or discard image process actions
	 * @return	object 
	 */ 
	public function render($keep_actions = FALSE) 
	{ 
		$new_image = $this->image['file']; 
	
		// Separate the directory and filename 
		$dir  = pathinfo($new_image, PATHINFO_DIRNAME); 
		$file = pathinfo($new_image, PATHINFO_BASENAME); 
	
		// Normalize the path 
		$dir = str_replace('\\', '/', realpath($dir)).'/'; 
	
		// Process the image with the driver 
		$status = $this->driver->process($this->image, $this->actions, $dir, $file, $render = TRUE); 
		
		// Reset actions. Subsequent save() or render() will not apply previous actions.
		if ($keep_actions === FALSE)
			$this->actions = array();
		
		return $status; 
	}

	/**
	 * Sanitize a given value type.
	 *
	 * @param   string   type of property
	 * @param   mixed    property value
	 * @return  boolean
	 */
	protected function valid_size($type, & $value)
	{
		if (is_null($value))
			return TRUE;

		if ( ! is_scalar($value))
			return FALSE;

		switch ($type)
		{
			case 'width':
			case 'height':
				if (is_string($value) AND ! ctype_digit($value))
				{
					// Only numbers and percent signs
					if ( ! preg_match('/^[0-9]++%$/D', $value))
						return FALSE;
				}
				else
				{
					$value = (int) $value;
				}
			break;
			case 'top':
				if (is_string($value) AND ! ctype_digit($value))
				{
					if ( ! in_array($value, array('top', 'bottom', 'center')))
						return FALSE;
				}
				else
				{
					$value = (int) $value;
				}
			break;
			case 'left':
				if (is_string($value) AND ! ctype_digit($value))
				{
					if ( ! in_array($value, array('left', 'right', 'center')))
						return FALSE;
				}
				else
				{
					$value = (int) $value;
				}
			break;
			case 'master':
				if ($value !== Image::NONE AND
				    $value !== Image::AUTO AND
				    $value !== Image::WIDTH AND
				    $value !== Image::HEIGHT)
					return FALSE;
			break;
		}

		return TRUE;
	}

} // End Image