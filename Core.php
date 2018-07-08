<?php
class Core{
	private $conn;
	public $sitename;
	function __construct(){
		require_once('init.php');
		$conn = new mysqli($db_host, $db_username, $db_password, $db_name);
		if($conn->connect_error) {
		    die("Connection failed: " . $conn->connect_error);
		} 
		$this->conn = $conn;
		$this->sitename = $sitename;
	}
	function __destruct(){
		$this->conn->close();
	}
	public function query($query){
		return $this->conn->query($query);
	}
	public function insertId(){
		return $this->conn->insert_id;
	}
	public function sqlError(){
		return $this->conn->error;
	}
	public function escape($str){
		return $this->conn->real_escape_string($str);
	}
	public function auth(){
		if(!isset($_SESSION['email']))
			$this->redirect('/',true);
	}
	public static function formSubmit($type = 'post') {
        switch($type) {
            case 'post':
                return (!empty($_POST)) ? true : false;
                break;
            case 'get':
                return (!empty($_GET)) ? true : false;
                break;
            default:
            	return (isset($_POST[$type])) ? true : false;
                break;
        }
    }
    public function formValue($item,$escape=true) {
        if(isset($_POST[$item])) {
            return ($escape) ? $this->escape($_POST[$item]) : $_POST[$item];
        } else if(isset($_GET[$item])) {
            return ($escape) ? $this->escape($_GET[$item]) : $_GET[$item];
        }
        return '';
    }
    public static function redirect($url,$php=false){
    	if($php){
    		header("location: $url");
    		exit();
    	}
    	else{
		?>
		<script type="text/javascript">
			window.location = "<?php echo $url;?>";
		</script>
		<?php
		}
	}
}
?>