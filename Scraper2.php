<?php
class Scraper2 extends Core{
	public $downloadStack = array();
	public $movieData = array();
	public $downloadLinks = array();
	public $catArray = array('Bollywood Movies','Hollywood Movies','Hollywood Dubbed Movies','Animated Movies','South Indian Dubbed Movies');
	function __construct(){
		parent::__construct();
	}

	function __destruct(){
		parent::__destruct();
	}

	public function scrap($url){
		$ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_POST, 0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, null);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.1) Gecko/20061204 Firefox/2.0.0.1");
    	curl_setopt($ch, CURLOPT_HTTPHEADER, Array("Content-Type:application/x-www-form-urlencoded"));
    	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
    	curl_setopt($ch, CURLOPT_BUFFERSIZE, 128); // more progress info
		curl_setopt($ch, CURLOPT_NOPROGRESS, false);
		curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function($Resource, $DownloadSize, $Downloaded, $UploadSize, $Uploaded){
		    // If $Downloaded exceeds 200KB, returning non-0 breaks the connection!
		    //echo 'DownloadSize:'.$DownloadSize.' Downloaded:'.$Downloaded.' Uploadsize:'.$UploadSize.' Upload:'.$Uploaded.'<br>';
		    return ($Downloaded > (200 * 1024)) ? 1 : 0;
		});
		//curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0); 
		//curl_setopt($ch, CURLOPT_TIMEOUT, $timeout); //timeout in seconds

    	//$info = curl_getinfo($ch);
    	//print_r($info);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
	}

/*******************************************************
		ALL MYCOOLMOVIEZ.PRO SCRAPPERS
********************************************************/

	//Fetching Data of Movie and store in database
	public function mycoolmoviez_fetchMovieData($url){
		$data = $this->scrap($url);
		$data = preg_replace('/\s+/', ' ', $data);

		//scrapping category
		preg_match('/<li><span>Category : <\/span><a href=".*?" title=".*?">(.*?)<\/a><\/li>/',$data,$arr);
		$details['category'] = trim($arr[1]);
		echo $details['category'];
		//If category allowed to scrap
		if(in_array($details['category'], $this->catArray)){
			$details['scrap_url'] = $url;
			//scrapping movie details
			preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/',$data,$arr);
			$json = json_decode($arr[1]);
			$details['link'] = preg_replace('/[^A-Za-z0-9]/', '-', $json->name);
			$details['name'] = $json->name;
			$details['description'] = $json->description;
			$details['thumbnail'] = $json->image->url;
			$details['artist'] = $json->actor;

			preg_match('/<li><span>Source : <\/span>(.*?)<\/li>/',$data,$arr);
			$details['quality'] = $arr[1];

			preg_match('/<li><span>Genre : <\/span>(.*?)<\/li>/',$data,$arr);
			$data2=$arr[1];
			preg_match_all('/<a href=".*?" title=".*?">(.*?)<\/a>/',$data2,$arr);
			$arr = implode(', ',$arr[1]);
			$details['genres'] = $arr;

			$details['release_date'] = $json->releasedEvent->startDate;

			//scrapping download links
			$data = preg_replace('/.*?(<input type="submit" name="submit_rate" value="Submit">)/', '$1' , $data);
			preg_match('/<div id="chead"><h3>.*?HD PC Downloads<\/h3><\/div> <div class="download"> <li><a href="(.*?)">.*?<b>Size : <\/b>(.*?) \]/',$data,$arr);
			if(!empty($arr))
				array_unshift($this->downloadStack,array($arr[1],$arr[2]));
			preg_match('/<div id="chead">.*?Best Print.*?<li><a href="(.*?)">.*?<b>Size : <\/b>(.*?) \]/',$data,$arr);
			if(!empty($arr))
				array_unshift($this->downloadStack,array($arr[1],$arr[2]));

			$this->movieData = $details;
			$details = $json = $data = null;

			//print_r($this->movieData);
			//echo '<br><br><br>';
			//print_r($this->downloadStack);

			if(!empty($this->downloadStack)){
				//function to scrap download links
				$this->mycoolmoviez_getDownloadLink();
				//funtion to add or update data in database for this movie
				$this->mycoolmoviez_addOrUpdate();
				echo '<br>'.$url.' Scrapped.';
			}
			else{
				//failed to fetch
				echo '<br><br>Failed To Fetch Video from <a href="'.$url.' target="_blank">LINK</a>!<br><br>';
				print_r($this->downloadStack);
			}
		}
		else{
			//failed to fetch
			echo '<br><br>Category Not Matched <a href="'.$url.' target="_blank">LINK</a>!<br><br>';
			print_r($this->movieData);
		}
	}

	//Fetching all download links
	private function mycoolmoviez_getDownloadLink(){
		$server = 1;
		if(!empty($this->downloadStack)){
			//print_r($this->downloadStack);
			foreach($this->downloadStack as $value){
				print_r($value);
				$data = $this->scrap($value[0]);
				$data = preg_replace('/\s+/', ' ', $data);
				preg_match('/<li class="l3">&raquo; <a .*? href="(.*?)" title=".*?>/',$data,$arr);
				$arr = $arr[1];
					$data = $this->scrap($arr,5);
					if(!empty($data)){
						$data = preg_replace('/\s+/', ' ', $data);
						preg_match('/<li class="l3"> <a rel="nofollow" href="(.*?)" target="_blank">/',$data,$arr);
						$arr=$arr[1];
					}
				$this->downloadLinks[] = array('server'=>'MyCoolMoviez Server '.$server, 'link'=>$arr, 'size'=>$value[1]);
				$server++;
			}
			print_r($this->downloadLinks);
			$this->downloadStack = array();
		}
	}
	
	private function mycoolmoviez_addOrUpdate(){
		$details = $this->movieData;
		$downloads = $this->downloadLinks;
		//print_r($details);
		//print_r($downloads);
		$link = $this->escape($details['link']);
		$scrap_url = $this->escape($details['scrap_url']);
		$name = $this->escape($details['name']);
		$description = $this->escape($details['description']);
		$thumbnail = $details['thumbnail'];
		$artist = $this->escape($details['artist']);
		$quality = $this->escape($details['quality']);
		$genres = $this->escape($details['genres']);
		$uploaded_on = strtotime("now");
		$release_date = date('Y-m-d',strtotime($details['release_date']));
		$category = $this->escape($details['category']);

		$check = $this->query("SELECT `id` FROM `movie` WHERE `link`='$link' AND `category`='$category'");
		if($check->num_rows > 0){
			//Update
			$check = $check->fetch_assoc();
			$id = $check['id'];
			$this->query("UPDATE `movie` SET `name`='$name',`scrap_url`='$scrap_url',`description`='$description',`artist`='$artist',`quality`='$quality',`genres`='$genres',`uploaded_on`='$uploaded_on',`release_date`='$release_date' WHERE `link`='$link' AND `category`='$category'");
			$this->query("DELETE FROM `download` WHERE `movie_id`='$id'");
			echo $this->sqlError();
			foreach ($downloads as $value) {
				$server = $value['server'];
				$link = $value['link'];
				$size = $value['size'];
				if(!empty($link))
					$this->query("INSERT INTO `download` SET `movie_id`='$id',`server`='$server',`link`='$link',`size`='$size'");
			}
		}
		else{
			//Insert
			file_put_contents($_SERVER['DOCUMENT_ROOT'].'/thumbs/'.$link.'.jpg',file_get_contents($thumbnail));
			$thumbnail = $link.'.jpg';
			$this->query("INSERT INTO `movie` SET `link`='$link',`scrap_url`='$scrap_url',`name`='$name',`description`='$description',`thumbnail`='$thumbnail',`artist`='$artist',`quality`='$quality',`genres`='$genres',`uploaded_on`='$uploaded_on',`release_date`='$release_date',`category`='$category'");
			echo $this->sqlError();
			$id = $this->insertId();
			foreach ($downloads as $value) {
				$server = $value['server'];
				$link = $value['link'];
				$size = $value['size'];
				if(!empty($link))
					$this->query("INSERT INTO `download` SET `movie_id`='$id',`server`='$server',`link`='$link',`size`='$size'");
			}
		}
		$this->movieData = array();
		$this->downloadLinks = array();
		//echo $this->sqlError();
	}

	
	//Getting list of movies and store in $stack
	private function mycoolmoviez_generateMovieList($url,$page=0){
		if($page==0){
			$this->mycoolmoviez_getMovieList($url);
			if(!empty($this->stack)){
				foreach ($this->stack as $value) {
					$this->mycoolmoviez_fetchMovieData($value);
				}
			}
		}
		else{
			for($i=1;$i<=$page;$i++){
				echo $url.'page/'.$i.'/<br>';
				$this->mycoolmoviez_getMovieList($url.'page/'.$i.'/');
			}
			file_put_contents($_SERVER['DOCUMENT_ROOT'].'/temp.txt', json_encode($this->stack));
		}
		//print_r($this->stack);
	}

	private function mycoolmoviez_getMovieList($url){
			$data = $this->scrap($url);
			$data = preg_replace('/\s+/', ' ', $data);
			preg_match('/<ul class="cat_ul">(.*?)<\/ul>/',$data,$arr);
			$data = $arr[1];
			$arr=null;
			preg_match_all('/<a href="(.*?)">(.*?) \[.*?\]<\/a>/',$data,$arr);
			//print_r($arr);
			$titles = $arr[2];
			$arr = $arr[1];

			$this->mycoolmoviez_insertIntoStack($arr,$titles);
	}

	private function mycoolmoviez_insertIntoStack($arr,$titles = array()){
		if(!empty($arr) && is_array($arr)){
			foreach ($arr as $key => $value) {
				if(isset($titles[$key]) && !empty($titles[$key])){
					$title = $this->escape($titles[$key]);
					//echo $this->query("SELECT * FROM `movie` WHERE `name`='$title'")->num_rows.' '.$title.'<br>';
					if($this->query("SELECT * FROM `movie` WHERE `name`='$title'")->num_rows > 0)
						continue;
				}
				array_unshift($this->stack,$value);
			}
		}
		//print_r($this->stack);
	}
}
?>