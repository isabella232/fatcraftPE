<?php
namespace fatutils\tools;

use fatutils\FatUtils;
use pocketmine\entity\Skin;
use pocketmine\Player;
use pocketmine\entity\Human;
use pocketmine\utils\Config;
/**
 * Class API
 * @package xenialdan\skinapi
 */
class SkinUtils{
	/**
	 * TODO
	 */
	const MULTIPLIER_LEFT = [];
	const MULTIPLIER_RIGHT = [];
	const MULTIPLIER_TOP = [];
	const MULTIPLIER_BOTTOM = [];
	const MULTIPLIER_FRONT = [];
	const MULTIPLIER_BACK = [];
	/**
	 * @param Skin $skin
	 * @return array
	 */
	public static function jsonSerialize(Skin $skin){
		return [
			"skinId" => $skin->getSkinId(),
			"skinData" => $skin->getSkinData(),
			"capeData" => $skin->getCapeData(),
			"geometryName" => $skin->getGeometryName(),
			"geometryData" => $skin->getGeometryData(),
		];
	}
	/**
	 * @param Skin $skin
	 * @param string $path
	 */
	public static function saveSkin(Skin $skin, string $path){
		$config = new Config($path);
		$config->setAll([$skin->getSkinId() => [$skin->getSkinData(), $skin->getGeometryData()]]);
		$config->save();
		$img = self::toImage($skin->getSkinData());
		imagepng($img, $path);
	}
	/**
	 * @param Player $player
	 * @param string $path
	 */
	public static function saveSkinOfPlayer(Player $player, string $path){
		self::saveSkin($player->getSkin(), $path);
	}
	/**
	 * @param array ...$parts
	 * @return mixed
	 */
	public static function mergeParts(...$parts){
		$baseimg = $parts[0];
		$base = imagecreatetruecolor(imagesx($baseimg), imagesy($baseimg));
		#imagealphablending($base, false);
		imagesavealpha($base, true);
		foreach ($parts as $part){
			$img = $part;
			imagecopy($base, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
		}
		return $base;
	}
	/**
	 * NOTICE: Only merges the images, not the json data!
	 * @param Skin[] $skins
	 * @return resource
	 */
	public static function mergeSkinsToImage(...$skins){
		$baseskin = $skins[0];
		$baseimg = self::toImage($baseskin->getSkinData());
		$base = imagecreatetruecolor(imagesx($baseimg), imagesy($baseimg));
		imagesavealpha($base, true);
		imagefill($base, 0, 0, imagecolorallocatealpha($base, 0, 0, 0, 127));
		foreach ($skins as $skin){
			$img = self::toImage($skin->getSkinData());
			imagecopy($base, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
		}
		return $base;
	}
	/**
	 * NOTICE: Only merges the images, not the json data!
	 * @param string[] $skinDataSets
	 * @return string
	 */
	public static function mergeSkinData(...$skinDataSets){
		$baseskin = $skinDataSets[0];
		$baseimg = self::toImage($baseskin);
		$base = imagecreatetruecolor(imagesx($baseimg), imagesy($baseimg));
		imagesavealpha($base, true);
		imagefill($base, 0, 0, imagecolorallocatealpha($base, 0, 0, 0, 127));
		foreach ($skinDataSets as $skinData){
			$img = self::toImage($skinData);
			imagecopy($base, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
		}
		return self::fromImage($base);
	}
	/**
	 * @param Skin $skin
	 * @param bool $showhat
	 * @return mixed
	 */
	public static function getHead(Skin $skin, $showhat = false){
		$head = self::getPart($skin, 'head');
		if ($showhat){
			return self::mergeParts($head, self::getPart($skin, 'hat'));
		}
		return self::mergeParts($head);
	}
	/**
	 * @param Skin $skin
	 * @param $partname
	 * @param array $side
	 * @return resource
	 */
	public static function getPart(Skin $skin, $partname, $side = self::MULTIPLIER_FRONT){//TODO SIDE
		$skindata = $skin->getSkinData();
		$img = self::toImage($skindata);
		imagealphablending($img, false);
		imagesavealpha($img, true);
		$skingeometry = $skin->getGeometryData();
		$son = json_decode($skingeometry, true);
		/* Head */
		$res = self::search($son, 'name', $partname);
		$partgeometry = $res[0];
		$startpos = $partgeometry["cubes"][0]["uv"];//0x 1y
		$size = $partgeometry["cubes"][0]["size"];//0x 1y 2z
		$startpos[0] = $startpos[0] + $size[2];//add the depth of the head because the left side comes first
		$startpos[1] = $startpos[1] + $size[1];//add the height of the head because the top comes first
		$part = imagecreatetruecolor($size[0], $size[1]);//create helmet of the size of the front//TODO fix correct sides
		imagealphablending($part, false);
		imagesavealpha($part, true);
		imagecopy($part, $img, 0, 0, $startpos[0], $startpos[1], $size[0], $size[1]);
		return $part;
	}
	/**
	 * @param Skin $skin
	 * @param $json
	 * @return string
	 */
	public static function addJSONtoExistingSkin(Skin $skin, $json){
		$skingeometry = $skin->getGeometryData();
		$base = json_decode($skingeometry, true);
		$json = str_replace('%s', $skin->getGeometryName(), $json);
		$extension = self::json_clean_decode($json, true);
		$finished = json_encode(array_merge($base, $extension));
		return $finished;
	}
	/**
	 * @param $data
	 * @param int $height
	 * @param int $width
	 * @return resource
	 */
	public static function toImage($data, $height = 64, $width = 64){
		$pixelarray = str_split(bin2hex($data), 8);
		$image = imagecreatetruecolor($width, $height);
		imagealphablending($image, false);//do not touch
		imagesavealpha($image, true);
		$position = count($pixelarray) - 1;
		while (!empty($pixelarray)){
			$x = $position % $width;
			$y = ($position - $x) / $height;
			$walkable = str_split(array_pop($pixelarray), 2);
			$color = array_map(function ($val){ return hexdec($val); }, $walkable);
			$alpha = array_pop($color); // equivalent to 0 for imagecolorallocatealpha()
			$alpha = ((~((int)$alpha)) & 0xff) >> 1; // back = (($alpha << 1) ^ 0xff) - 1
			array_push($color, $alpha);
			imagesetpixel($image, $x, $y, imagecolorallocatealpha($image, ...$color));
			$position--;
		}
		return $image;
	}
	/**
	 * @param resource $img
	 * @return string
	 */
	public static function fromImage($img){
		$combine = [];
		for ($y = 0; $y < imagesy($img); $y++){
			for ($x = 0; $x < imagesx($img); $x++){
				$color = imagecolorsforindex($img, imagecolorat($img, $x, $y));
				//TODO fix uneven alpha - if even possible..
				$color['alpha'] = (($color['alpha'] << 1) ^ 0xff) - 1; // back = (($alpha << 1) ^ 0xff) - 1
				$combine[] = sprintf("%02x%02x%02x%02x", $color['red'], $color['green'], $color['blue'], $color['alpha']??0);
			}
		}
		$data = hex2bin(implode('', $combine));
		return $data;
	}

        public static function fromPNG($path)
        {
            $srcImage = imagecreatefrompng( $path );
            return $srcImage;
        }

        public static function applySkin(Human $human, $path, $geometry)
        {
            $skinData = self::fromImage(self::fromPNG($path));
            $geometryConf = new Config($geometry, Config::JSON);
            $geometryData = $geometryConf->getAll();
            $skin = new \pocketmine\entity\Skin(
                "test_1",
                $skinData,
                "",
                "geometry.humanoid.custom",
                json_encode($geometryData)
            );
            $human->setSkin($skin);
        }

        public static function getSkin($path, $geometry)
        {
            $skinData = self::fromImage(self::fromPNG($path));
            $geometryConf = new Config($geometry, Config::JSON);
            $geometryData = $geometryConf->getAll();
            $skin = new \pocketmine\entity\Skin(
                "test_1",
                $skinData,
                "",
                "geometry.humanoid.custom",
                json_encode($geometryData)
            );
            return $skin;
        }

//        public static function getSkin($theme, $name)
//        {
//            $path = FatUtils::getInstance()->getDataFolder() . "/skinpacks/" . $theme . "/";
//            $geometriesConf = new Config($path . "skins.json", Config::JSON);
//            $skinData = self::fromImage(self::fromPNG($path));
//            
//            $geometryConf = new Config($geometry, Config::JSON);
//            $geometryData = $geometryConf->getAll();
//            $skin = new \pocketmine\entity\Skin(
//                "test_1",
//                $skinData,
//                "",
//                "geometry.humanoid.custom",
//                json_encode($geometryData)
//            );
//            return $skin;
//        }

        //Skin repository (in resources)
        public static function listSkins()
        {
//            if(is_dir(FatUtils::getInstance()->getPluginFile() . "resources/skinpacks/")){
////                FatUtils::getInstance()->saveResource("skinpacks/skins.json");
////                $skinList = new Config("skinpacks/skins.json", Config::JSON);
//                foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(FatUtils::getInstance()->getPluginFile() . "resources/skinpacks/")) as $resource){
//                    if (is_dir($resource))
//                    {
//                        echo $resource . "\n";
//                    }
//                }
//            }
            if (is_dir(FatUtils::getInstance()->getDataFolder() . "skinpacks/"))
            {
                foreach(new \IteratorIterator(new \DirectoryIterator(FatUtils::getInstance()->getDataFolder() . "/skinpacks/")) as $skinsPack)
                {
                    if ($skinsPack !== "." && $skinsPack !== "..")
                    echo $skinsPack . "\n";
                }
            }
        }
	/**
	 * @param $array
	 * @param $key
	 * @param $value
	 * @return array
	 */
	public static function search($array, $key, $value){
		$results = array();
		self::search_r($array, $key, $value, $results);
		return $results;
	}
	/**
	 * @param $array
	 * @param $key
	 * @param $value
	 * @param $results
	 */
	public static function search_r($array, $key, $value, &$results){
		if (!is_array($array)){
			return;
		}
		if (isset($array[$key]) && $array[$key] == $value){
			$results[] = $array;
		}
		foreach ($array as $subarray){
			self::search_r($subarray, $key, $value, $results);
		}
	}
	/**
	 * @param $json
	 * @param bool $assoc
	 * @param int $depth
	 * @param int $options
	 * @return mixed
	 */
	public static function json_clean_decode($json, $assoc = false, $depth = 512, $options = 0){
		// search and remove comments like /* */ and //
		$json = preg_replace("#(/\*([^*]|[\r\n]|(\*+([^*/]|[\r\n])))*\*+/)|([\s\t]//.*)|(^//.*)#", '', $json);
		if (version_compare(phpversion(), '5.4.0', '>=')){
			return json_decode($json, $assoc, $depth, $options);
		} elseif (version_compare(phpversion(), '5.3.0', '>=')){
			return json_decode($json, $assoc, $depth);
		} else{
			return json_decode($json, $assoc);
		}
	}
}