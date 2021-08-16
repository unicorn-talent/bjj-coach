<?php

namespace App;

use Image;
use App\Models\AdminSettings;
use App\Models\PaymentGateways;
use Illuminate\Support\Facades\Storage;

class Helper
{
	// spaces
	public static function spacesUrlFiles($string) {
	  return ( preg_replace('/(\s+)/u','_',$string ) );

	}

	public static function spacesUrl($string) {
	  return ( preg_replace('/(\s+)/u','+',trim( $string ) ) );

	}

	public static function removeLineBreak($string)  {
		return str_replace(array("\r\n", "\r"), "", $string);
	}

    public static function hyphenated($url)
    {
        $url = strtolower($url);
        //Rememplazamos caracteres especiales latinos
        $find = array('á','é','í','ó','ú','ñ');
        $repl = array('a','e','i','o','u','n');
        $url = str_replace($find,$repl,$url);
        // Añaadimos los guiones
        $find = array(' ', '&', '\r\n', '\n', '+');
                $url = str_replace ($find, '-', $url);
        // Eliminamos y Reemplazamos demás caracteres especiales
        $find = array('/[^a-z0-9\-<>]/', '/[\-]+/', '/<[^>]*>/');
        $repl = array('', '-', '');
        $url = preg_replace ($find, $repl, $url);
        //$palabra=trim($palabra);
        //$palabra=str_replace(" ","-",$palabra);
        return $url;
        }

	// Text With (2) line break
	public static function checkTextDb($str) {

		if(mb_strlen($str, 'utf8') < 1) {
			return false;
		}
		$str = preg_replace('/(?:(?:\r\n|\r|\n)\s*){3}/s', "\r\n\r\n", $str);
		$str = trim($str,"\r\n");

		return $str;
	}

	public static function checkText($str, $url = null) {

		if(mb_strlen($str, 'utf8') < 1) {
			return false;
		}

		$str = str_replace($url, '', $str);

		$str = trim($str);
		$str = nl2br(e($str));
		$str = str_replace(array(chr(10), chr(13) ), '' , $str);
		$url = preg_replace('#^https?://#', '', url('').'/');

		$regex = "~([@])([^\s@!\"\$\%&\'\(\)\*\+\,\-./\:\;\<\=\>?\[/\/\/\\]\^\`\{\|\}\~]+)~";
		$str = preg_replace($regex, '<a href="//'.$url.'$2">$0</a>', $str);

		$str = stripslashes($str);
		return $str;
	}

	public static function formatNumber( $number ) {
    if( $number >= 1000 &&  $number < 1000000 ) {

       return number_format( $number/1000, 1 ). "k";
    } else if( $number >= 1000000 ) {
		return number_format( $number/1000000, 1 ). "M";
	} else {
        return $number;
    }
   }//<<<<--- End Function

	 public static function formatNumbersStats( $number ) {

    if( $number >= 100000000 ) {
		return '<span class="counterStats">'.number_format( $number/1000000, 0 ). "</span>M";
	} else {
        return '<span class="counterStats">'.number_format( $number ).'</span>';
    }
   }//<<<<--- End Function

   public static function spaces($string) {
	  return ( preg_replace('/(\s+)/u',' ',$string ) );

	}

	public static function resizeImage( $image, $width, $height, $scale, $imageNew = null ) {

		list($imagewidth, $imageheight, $imageType) = getimagesize($image);
	$imageType = image_type_to_mime_type($imageType);
	$newImageWidth = ceil($width * $scale);
	$newImageHeight = ceil($height * $scale);
	$newImage = imagecreatetruecolor($newImageWidth,$newImageHeight);
	switch($imageType) {
		case "image/gif":
			$source=imagecreatefromgif($image);
			imagefill( $newImage, 0, 0, imagecolorallocate( $newImage, 255, 255, 255 ) );
			imagealphablending( $newImage, TRUE );
			break;
	    case "image/pjpeg":
		case "image/jpeg":
		case "image/jpg":
			$source=imagecreatefromjpeg($image);
			break;
	    case "image/png":
		case "image/x-png":
			$source=imagecreatefrompng($image);
			imagealphablending( $newImage, false );
			imagesavealpha( $newImage, true );
			break;
  	}
	imagecopyresampled($newImage,$source,0,0,0,0,$newImageWidth,$newImageHeight,$width,$height);

	switch($imageType) {
		case "image/gif":
	  		imagegif( $newImage, $imageNew );
			break;
      	case "image/pjpeg":
		case "image/jpeg":
		case "image/jpg":
	  		imagejpeg( $newImage, $imageNew ,90 );
			break;
		case "image/png":
		case "image/x-png":
			imagepng( $newImage, $imageNew );
			break;
    }

	chmod($image, 0777);
	return $image;
	}

public static function resizeImageFixed( $image, $width, $height, $imageNew = null ) {

	list($imagewidth, $imageheight, $imageType) = getimagesize($image);
	$imageType = image_type_to_mime_type($imageType);
	$newImage = imagecreatetruecolor($width,$height);

	switch($imageType) {
		case "image/gif":
			$source=imagecreatefromgif($image);
			imagefill( $newImage, 0, 0, imagecolorallocate( $newImage, 255, 255, 255 ) );
			imagealphablending( $newImage, TRUE );
			break;
	    case "image/pjpeg":
		case "image/jpeg":
		case "image/jpg":
			$source=imagecreatefromjpeg($image);
			break;
	    case "image/png":
		case "image/x-png":
			$source=imagecreatefrompng($image);
			imagefill( $newImage, 0, 0, imagecolorallocate( $newImage, 255, 255, 255 ) );
			imagealphablending( $newImage, TRUE );
			break;
  	}
	if( $width/$imagewidth > $height/$imageheight ){
        $nw = $width;
        $nh = ($imageheight * $nw) / $imagewidth;
        $px = 0;
        $py = ($height - $nh) / 2;
    } else {
        $nh = $height;
        $nw = ($imagewidth * $nh) / $imageheight;
        $py = 0;
        $px = ($width - $nw) / 2;
    }

	imagecopyresampled($newImage,$source,$px, $py, 0, 0, $nw, $nh, $imagewidth, $imageheight);

	switch($imageType) {
		case "image/gif":
	  		imagegif($newImage,$imageNew);
			break;
      	case "image/pjpeg":
		case "image/jpeg":
		case "image/jpg":
	  		imagejpeg($newImage,$imageNew,90);
			break;
		case "image/png":
		case "image/x-png":
			imagepng($newImage,$imageNew);
			break;
    }

		chmod($image, 0777);
		return $image;
	}

	public static function getHeight( $image ) {
		$size   = getimagesize( $image );
		$height = $size[1];
		return $height;
	}

	public static function getWidth( $image ) {
		$size  = getimagesize( $image);
		$width = $size[0];
		return $width;
	}
	public static function formatBytes($size, $precision = 2) {
    $base = log($size, 1024);
    $suffixes = array('', 'kB', 'MB', 'GB', 'TB');

    return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
  }

	public static function removeHTPP($string){
		$string = preg_replace('#^https?://#', '', $string);
		return $string;
	}

	public static function Array2Str( $kvsep, $entrysep, $a ){
		$str = "";
			foreach ( $a as $k => $v ){
				$str .= "{$k}{$kvsep}{$v}{$entrysep}";
				}
		return $str;
	}

	public static function removeBR($string) {
		$html    = preg_replace( '[^(<br( \/)?>)*|(<br( \/)?>)*$]', '', $string );
		$output = preg_replace('~(?:<br\b[^>]*>|\R){3,}~i', '<br /><br />', $html);
		return $output;
	}

	public static function removeTagScript( $html ){

			  	//parsing begins here:
				$doc = new \DOMDocument();
				@$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
				$nodes = $doc->getElementsByTagName('script');

				$remove = [];

				foreach ($nodes as $item) {
					$remove[] = $item;
				}

				foreach ($remove as $item) {
					$item->parentNode->removeChild($item);
				}

				return preg_replace(
					'/^<!DOCTYPE.+?>/', '',
					str_replace(
					array('<html>', '</html>', '<body>', '</body>', '<head>', '</head>', '<p>', '</p>', '&nbsp;' ),
					array('','','','','',' '),
					$doc->saveHtml() ));
	}// End Method

	public static function removeTagIframe( $html ){

			  	//parsing begins here:
				$doc = new \DOMDocument();
				@$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
				$nodes = $doc->getElementsByTagName('iframe');

				$remove = [];

				foreach ($nodes as $item) {
					$remove[] = $item;
				}

				foreach ($remove as $item) {
					$item->parentNode->removeChild($item);
				}

				return preg_replace(
					'/^<!DOCTYPE.+?>/', '',
					str_replace(
					array('<html>', '</html>', '<body>', '</body>', '<head>', '</head>', '<p>', '</p>', '&nbsp;' ),
					array('','','','','',' '),
					$doc->saveHtml() ));
	}// End Method

	public static function fileNameOriginal($string){
		return pathinfo($string, PATHINFO_FILENAME);
	}

	public static function formatDate($date, $time = false)
	{
		$settings = AdminSettings::first();

		if ($time == false) {
			$date = strtotime($date);
		}

		$day    = date('d', $date);
		$_month = date('m', $date);
		$month  = trans("months.$_month");
		$year   = date('Y', $date);

		if ($settings->date_format == 'M d, Y') {
			$dateFormat = $month.' '.$day.', '.$year;
		} elseif ($settings->date_format == 'd M, Y') {
			$dateFormat = $day.' '.$month.', '.$year;
		} else {
			$dateFormat = date($settings->date_format, $date);
		}

		return $dateFormat;
	}

	public static function watermark($name, $watermarkSource) {

		$thumbnail = Image::make($name);
		$watermark = Image::make($watermarkSource);
		$x = 0;

		while ($x < $thumbnail->width()) {
		    $y = 0;

		    while($y < $thumbnail->height()) {
		        $thumbnail->insert($watermarkSource, 'top-left', $x, $y);
		        $y += $watermark->height();
		    }

		    $x += $watermark->width();
		}

		$thumbnail->save($name)->destroy();
	}

	public static function amountFormat($value) {

		$settings = AdminSettings::first();

		if($settings->currency_position == 'left') {
			$amount = $settings->currency_symbol.number_format($value);
		} elseif($settings->currency_position == 'right') {
			$amount = number_format($value).$settings->currency_symbol;
		} else {
			$amount = $settings->currency_symbol.number_format($value);
		}

	 return $amount;

	}

	public static function amountWithoutFormat($value) {

		$settings = AdminSettings::first();

		if($settings->currency_position == 'left') {
			$amount = $settings->currency_symbol.$value;
		} elseif($settings->currency_position == 'right') {
			$amount = $value.$settings->currency_symbol;
		} else {
			$amount = $settings->currency_symbol.$value;
		}

	 return $amount;

	}

	public static function getYoutubeId($url) {
	 $pattern =
			 '%^# Match any youtube URL
			(?:https?://)?
			(?:www\.)?
			(?:
				youtu\.be/
			| youtube\.com
				(?:
					/embed/
				| /v/
				| .*v=
				)
			)
			([\w-]{10,12})
			($|&).*
			$%x'
			;

			$result = preg_match( $pattern, $url, $matches );
			if ( $matches ) {
					return $matches[1];
			}
			return false;
	}//<<<-- End

	public static function getVimeoId($url)
	{

		$url = explode('/',$url);
		return $url[3];
	}

	public static function videoUrl($url)
	{
		$urlValid = filter_var($url, FILTER_VALIDATE_URL) ? true : false;

		if ($urlValid) {
			$parse = parse_url($url);
			$host  = strtolower($parse['host']);

			if ($host) {
				if (in_array($host, array(
					'youtube.com',
					'www.youtube.com',
					'youtu.be',
					'www.youtu.be',
					'vimeo.com',
					'player.vimeo.com'))) {
						return $host;
				}
			}
		}
	}

	//============== linkText
	 public static function linkText($text) {
	    return preg_replace('/https?:\/\/[\w\-\.!~#?&=+%;:\*\'"(),\/]+/u','<a class="data-link" href="$0" target="_blank">$0</a>', $text);
	}

	public static function strRandom() {
		return substr( strtolower( md5( time() . mt_rand( 1000, 9999 ) ) ), 0, 8 );
	}// End method

	public static function amountFormatDecimal($value)
  {
 	 $settings = AdminSettings::first();

	 if ($settings->currency_code == 'JPY') {
		 return $settings->currency_symbol.number_format($value);
	 }

 	 if ($settings->decimal_format == 'dot') {
 		 $decimalDot = '.';
 		 $decimalComma = ',';
 	 } else {
 		 $decimalDot = ',';
 		 $decimalComma = '.';
 	 }

 	 if ($settings->currency_position == 'left') {
 		 $amount = $settings->currency_symbol.number_format($value, 2, $decimalDot, $decimalComma);
 	 } elseif ($settings->currency_position == 'right') {
 		 $amount = number_format($value, 2, $decimalDot, $decimalComma).$settings->currency_symbol;
 	 } else {
 		 $amount = $settings->currency_symbol.number_format($value, 2, $decimalDot, $decimalComma);
 	 }

 	return $amount;

 }// END

 public static function envUpdate($key, $value, $comma = false)
  {
      $path = base_path('.env');

			// Change permissions
			chmod($path, 0644);

			$value = trim($value);
			$env = $comma ? '"'.env($key).'"' : env($key);

      if (file_exists($path)) {

          file_put_contents($path, str_replace(
              $key . '=' . $env, $key . '=' . $value, file_get_contents($path)
          ));
      }
  }

	public static function urlToDomain($url)
	{
   $domain = explode('/', preg_replace('/https?:\/\/(www\.)?/', '', $url));
   return $domain['0'];
 }

 public static function expandLink($url)
 {
	 $headers = get_headers($url, 1);

	 if (! empty($headers['Location'])) {
		 $headers['Location'] = (array) $headers['Location'];
		 $url = array_pop($headers['Location']);
	 }
	 return $url;
 }

 public static function getFirstUrl($string)
 {
	 preg_match_all('#\bhttps?://[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/))#', $string, $_matches);

		 $firstURL = $_matches[0][0] ?? false;

	 if ($firstURL) {
			return $firstURL;
		 }
 }

 public static function daysInMonth($month, $year)
 {
    return $month == 2 ? ($year % 4 ? 28 : ($year % 100 ? 29 : ($year % 400 ? 28 : 29))) : (($month - 1) % 7 % 2 ? 30 : 31);
	}

	public static function PercentageIncreaseDecrease($currentPeriod, $previousPeriod)
	{
		if ($currentPeriod > $previousPeriod && $previousPeriod != 0) {
			 $subtraction = $currentPeriod  - $previousPeriod;
			 $percentage = $subtraction / $previousPeriod * 100;
			 return '<small class="float-right text-success">
			 <strong><i class="feather icon-arrow-up mr-1"></i> '.number_format($percentage, 1).'%</strong>
			 </small>';

		} elseif ($currentPeriod < $previousPeriod && $previousPeriod != 0) {
			$subtraction = $previousPeriod - $currentPeriod;
			$percentage = $subtraction / $previousPeriod * 100;
			return '<small class="float-right text-danger">
			<strong><i class="feather icon-arrow-down mr-1"></i> '.number_format($percentage, 1).'%</strong>
			</small>';

		} elseif ($currentPeriod == $previousPeriod) {
			return '<small class="float-right text-muted">
			<strong><i class="feather icon-arrow-left mr-1"></i> 0%</strong>
			</small>';

		} else {
			 $percentage = $currentPeriod / 100 * 100;
			return '<small class="float-right text-success">
			<strong><i class="feather icon-arrow-up mr-1"></i> '.number_format($percentage, 1).'%</strong>
			</small>';

		}
	}

	public static function getFile($path)
	{
		if (env('FILESYSTEM_DRIVER') == 'backblaze') {
			 return 'https://'.env('BACKBLAZE_BUCKET').'.'.env('BACKBLAZE_BUCKET_REGION').'/'.$path;
		} else {
			return Storage::url($path);
		}
	}

	public static function showSectionMyCards()
	{
		return PaymentGateways::whereName('Stripe')
			 ->whereEnabled('1')
			 ->orWhere('name', 'Paystack')
			 ->whereEnabled('1')
		 ->first() ? true : false;
	}

	private static function getPool($type = 'alnum')
	{
			switch ($type) {
					case 'alnum':
							$pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
							break;
					case 'alpha':
							$pool = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
							break;
					case 'hexdec':
							$pool = '0123456789abcdef';
							break;
					case 'numeric':
							$pool = '0123456789';
							break;
					case 'nozero':
							$pool = '123456789';
							break;
					case 'distinct':
							$pool = '2345679ACDEFHJKLMNPRSTUVWXYZ';
							break;
					default:
							$pool = (string) $type;
							break;
			}

			return $pool;
	}

	/**
	 * Generate a random secure crypt figure
	 * @param  integer $min
	 * @param  integer $max
	 * @return integer
	 */
	private static function secureCrypt($min, $max)
	{
			$range = $max - $min;

			if ($range < 0) {
					return $min; // not so random...
			}

			$log    = log($range, 2);
			$bytes  = (int) ($log / 8) + 1; // length in bytes
			$bits   = (int) $log + 1; // length in bits
			$filter = (int) (1 << $bits) - 1; // set all lower bits to 1
			do {
					$rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));
					$rnd = $rnd & $filter; // discard irrelevant bits
			} while ($rnd >= $range);

			return $min + $rnd;
	}

	/**
	 * Finally, generate a hashed token
	 * @param  integer $length
	 * @return string
	 */
	public static function getHashedToken($length = 25)
	{
			$token = "";
			$max   = strlen(static::getPool());
			for ($i = 0; $i < $length; $i++) {
					$token .= static::getPool()[static::secureCrypt(0, $max)];
			}

			return $token;
	}

	public static function genTranxRef()
	{
			return self::getHashedToken();
	}

}//<--- End Class
