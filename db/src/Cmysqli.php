<?php
namespace Yum\Db;
/**
 * 用途：替代原cdb类，PHP7.0以后的版本使用mysqli
 *
 */
class Cmysqli{
	public $count = 0; //查询次数
	public $aServer = 0; //
	private $persist = false; //是否长连接
	private $die = true; //有SQL错误时是否退出脚本
	private $connect = false; //是否连接上
	private $connected = false; //是否已经连接过
	private $connectlast = 0; //记录最后连接的时间.每隔一段时间强制连一次.受限于wait_timeout配置
	private $exec_sql = null; //当前执行的SQL语句

	private $mysqli = null;
	private $mysqli_result = null;
	private $host = null;
	private $username = null;
	private $passwd = null;
	private $dbname = null;
	private $port = null;
	private $socket = null;
	
	/**
	 * 初始化
	 * @param Array $aServer array('localhost', 'port', 'username', 'password', 'database_name')
	 * @param Boolean $persist 是否长连接
	 * @param Boolean $die 有sql错误时是否退出脚本
	 */
	public function __construct( $aServer, $persist=false, $die=true){
		$this->aServer = $aServer;
		$this->persist = $persist;
		$this->die = $die;

		$this->host = $this->persist ? 'p:' . $aServer[0] : $aServer[0];
		$this->port = $aServer[1];
		$this->username = $aServer[2];
		$this->passwd = $aServer[3];
		$this->dbname = $aServer[4] ? $aServer[4] : '';
	}
	
	/**
	 * 打开一个到 MySQL 服务器的连接
	 * @return Boolean
	 */
	public function connect(){
		$this->count++; //统计查询次数
		if(! $this->connected){
			$this->connected = true; //标志已经连接过一次
			$this->connectlast = time(); //记录此次连接的时间
			for($try = 0; $try < 3; $try++){
				try{
					$this->mysqli = @new mysqli($this->host, $this->username, $this->passwd, '', $this->port, $this->socket);
					if(is_object( $this->mysqli) && $this->mysqli->thread_id){
						$this->connect = true;
					}
				}catch (Exception $e){}//连接失败,记录
				//连接上 则退出循环
				if($this->connect === true){
					break;
				}
			}
			$this->dbname && $this->select_db($this->dbname);
			if(mysqli_connect_errno() || $this->connect === false){
				$this->errorlog('Connect:'.mysqli_connect_error().',Try:'.$try, true);
			}
		}
		return $this->connect;
	}
	/**
	 * 取得最近一次 INSERT,UPDATE 或 DELETE 所影响的记录行数.如果最近一次查询失败的话,函数返回 -1.
	 * UPDATE: 只有真正被修改的记录数才会被返回
	 * REPLACE: 返回的是被删除的记录数加上被插入的记录数.
	 * Transactions: 需要在 INSERT,UPDATE 或 DELETE 查询后调用此函数,而不是在 COMMIT 命令之后. 
	 * @return int 错误或连不上返回-1
	 */
	public function affected_rows(){
		return (int)$this->mysqli->affected_rows;
	}
	/**
	 * 返回字符集的名称
	 * @return String
	 */
	public function client_encoding(){
		return $this->connect() ? $this->mysqli->character_set_name() : '';
	}
	public function character_set_name(){
		return $this->connect() ? $this->mysqli->character_set_name() : '';
	}
	/**
	 * 关闭 MySQL 非持久连接
	 * @return Boolean
	 */
	public function close(){
		if($this->connected){
			$this->connected = false;
			$ret = (bool)$this->mysqli->close();
			$this->connectlast = 0;
			$this->mysqli = $this->mysqli_result = null;
		}
		return true;
	}
	/**
	 * 移动内部结果的指针.只能和 mysql_query() 结合起来使用,而不能用于 mysql_unbuffered_query()
	 * @return Boolean
	 */
	public function data_seek($offset){
		return (bool)$this->call(array('mysqli_result', 'data_seek'), array((int)$offset));
	}
	/**
	 * 取得 mysql_list_dbs() 调用所返回的数据库名
	 */
	public function db_name($result, $row, $field=null){

	}
	/**
	 * 返回上一个 MySQL 操作中的错误信息的数字编码.如果没有出错则返回 0
	 * @return int
	 */
	public function errno(){
		return $this->connect() ? (int)$this->mysqli->errno : 0;
	}
	/**
	 * 返回上一个 MySQL 操作产生的文本错误信息.如果没有错误则返回 ''
	 * @return String
	 */
	public function error(){
		return $this->connect() ? (string)$this->mysqli->error : '';
	}
	/**
	 * 转义一个字符串用于 query
	 * @return String
	 */
	public function escape_string( $unescaped_string){
		return (PHP_VERSION > '5.3.0' ) ? addslashes( trim( $unescaped_string)) : mysqli_escape_string( $this->mysqli, trim( $unescaped_string) );
	}
	/**
	 * @var int $resulttype MYSQLI_ASSOC/MYSQLI_NUM/MYSQLI_BOTH
	 * @return array
	 */
	public function fetch_all($resulttype=MYSQLI_ASSOC){
		return (array)$this->call(array('mysqli_result', 'fetch_all'), array((int)$resulttype));
	}
	/**
	 * 从结果集中取得一行作为关联数组，或数字数组，或二者兼有(带指针移动)
	 * @return Array
	 */
	public function fetch_array($result, $result_type=MYSQLI_ASSOC){
		$result_type = in_array($result_type, array(MYSQLI_ASSOC, MYSQLI_NUM, MYSQLI_ASSOC)) ? $result_type : MYSQLI_ASSOC;
		$ret = $this->call(array('mysqli_result', 'fetch_array'), array((int)$result_type));
		return is_array($ret) ? $ret : array();
	}
	/**
	 * 从结果集中取得列信息并作为对象返回
	 * @param $field_offset int
	 * @return Object/false
	 */
	public function fetch_field($result, $field_offset=null){
		return (array)$this->call(array('mysqli_result', 'fetch_field'), array());
	}
	/**
	 * @return array
	 */
	public function fetch_fields(){
		return (array)$this->call(array('mysqli_result', 'fetch_fields'), array());
	}
	/**
	 * @return array
	 */
	public function fetch_field_direct($fieldnr){
		return (array)$this->call(array('mysqli_result', 'fetch_field_direct'), array((int)$fieldnr));
	}
	/**
	 * 返回上一次用 fetch_*() 取得的行中每个字段的长度.必须要在 fetch_* 之后再执行此方法
	 * @return Array
	 */
	public function fetch_lengths( $result){
		$ret = $this->mysqli_result->lengths;
		return is_array($ret) ? $ret : array();
	}
	/**
	 * 从结果集中取得一行作为对象(带指针移动)
	 * @return Object/false
	 */
	public function fetch_object( $result){
		return $this->call(array('mysqli_result', 'fetch_object'), array());
	}
	/**
	 * 从结果集中取得一行作为枚举数组(带指针移动)
	 * @return Array
	 */
	public function fetch_row( $result){
		return (array)$this->call(array('mysqli_result', 'fetch_row'), array());
	}
	/**
	 * @return int
	 */
	public function field_count(){
		return (int)$this->mysqli->field_count;
	}
	/**
	 * 从结果集中取得一行作为关联数组(带指针移动)
	 * @return Array
	 */
	public function fetch_assoc( $result){
		return (array)$this->call(array('mysqli_result', 'fetch_assoc'), array());
	}
	/**
	 * 从结果中取得和指定字段关联的标志
	 * @return String
	 */
	public function field_flags($result, $field_offset){
		//return is_resource( $result) && ($result = @mysql_field_flags($result, $field_offset)) ? $result : '';
	}
	/**
	 * 返回结果中指定字段的长度.指字节,如UTF-8则为宽度*3
	 * @param $field_offset int 第几列字段
	 * @return int
	 */
	public function field_len($result, $field_offset){
		//return is_resource( $result) && ($result = @mysql_field_len($result, $field_offset)) ? $result : 0;
	}
	/**
	 * 取得结果中指定字段的字段名
	 * @param $field_index int 第几列字段
	 * @return String
	 */
	public function field_name($result, $field_index){
		//return is_resource( $result) && ($result = @mysql_field_name($result, $field_index)) ? $result : '';
	}
	/**
	 * 将结果集中的指针设定为制定的字段偏移量
	 * @return int
	 */
	public function field_seek($result, $field_offset){
		return (bool)$this->call(array('mysqli_result', 'field_seek'), array((int)$field_offset));
	}
	/**
	 * @return int
	 */
	public function field_tell(){
		return (int)$this->mysqli_result->current_field;
	}
	/**
	 * 取得结果集中指定字段所在的表名
	 * @param $field_offset int 第几列字段
	 * @return String
	 */
	public function field_table($result, $field_offset){
		//return is_resource( $result) && ($result = @mysql_field_table($result, $field_offset)) ? $result : '';
	}
	/**
	 * 取得结果集中指定字段的类型
	 * @param $field_offset int 第几列字段
	 * @return String
	 */
	public function field_type($result, $field_offset){
		//return is_resource( $result) && ($result = @mysql_field_type($result, $field_offset)) ? $result : '';
	}
	/**
	 * 释放结果内存
	 * @return Boolean
	 */
	public function free(){
		return $this->call(array('mysqli_result', 'free'), array());
	}
	/**
	 * 释放结果内存
	 * @return Boolean
	 */
	public function free_result( $result){
		return $this->call(array('mysqli_result', 'free_result'), array());
	}
	/**
	 * @return array
	 */
	public function get_charset(){
		return $this->connect() ? (array)$this->call(array('mysqli', 'get_charset'), array()) : array();
	}
	/**
	 * 取得 MySQL 客户端信息
	 * @return String
	 */
	public function get_client_info(){
		return $this->connect() ? (string)$this->call(array('mysqli', 'get_client_info'), array()) : '';
	}
	/**
	 * @return int
	 */
	public function get_client_version(){
		return $this->connect() ? (int)$this->mysqli->client_version : 0;
	}
	/**
	 * @return array
	 */
	public function get_connection_stats(){
		return ($ret = $this->call(array('mysqli', 'get_connection_stats'), array())) ? (array)$ret : array();
	}
	/**
	 * 取得 MySQL 主机信息
	 * @return String
	 */
	public function get_host_info(){
		return $this->connect() ? (string)$this->mysqli->host_info : '';
	}
	/**
	 * 取得 MySQL 协议信息
	 * @return int
	 */
	public function get_proto_info(){
		return $this->connect() ? (int)$this->mysqli->protocol_version : 0;
	}
	/**
	 * 取得 MySQL 服务器版本
	 * @return String
	 */
	public function get_server_info(){
		return $this->connect() ? (string)$this->mysqli->server_info : '';
	}
	/**
	 * @return int
	 */
	public function get_server_version(){
		return (int)$this->mysqli->server_version;
	}
	/**
	 * @return array
	 */
	public function get_warnings(){
		return $this->connect() ? ($ret = $this->call(array('mysqli', 'get_warnings'), array())) ? (array)$ret : array() : array();
	}
	/**
	 * 取得最近一条查询的信息.仅针对INSERT,UPDATE,ALTER,LOAD DATA INFILE
	 * @return String
	 */
	public function info(){
		return (string)$this->mysqli->info;
	}

	/**
	 * 取得上一步 INSERT 操作产生的 ID
	 * @return int 如果自增字段是bigint,则返回值会有问题
	 */
	public function insert_id(){
		return (int)$this->mysqli->insert_id;
	}
	/**
	 * @return boolean
	 */
	public function kill($processid){
		return (bool)$this->call(array('mysqli', 'kill'), array((int)$processid));
	}
	/**
	 * @return boolean
	 */
	public function more_results(){
		return (bool)$this->call(array('mysqli', 'more_results'), array());
	}
	/**
	 *  odb::db()->multi_query("SELECT 1;SELECT 2;SELECT 3;");
	do{
	echo '<p>---------------</p>';
	odb::db()->store_result(); //必须!把服务端结果集存储到客户端来;或者用odb::db()->use_result()结果集在服务端

	while ($array = odb::db()->fetch_assoc()){
	print_r($array);
	}
	}while (odb::db()->next_result());
	 * @return boolean 只返回第一条查询的状态
	 */
	public function multi_query($query){
		return (bool)$this->call(array('mysqli', 'multi_query'), array((string)$query));
	}
	public function next_result(){
		return (bool)$this->call(array('mysqli', 'next_result'), array());
	}
	//批量查询并返回结果
	public function multi_query_getAll($query){
		$result = array();
		$this->multi_query($query);
		$k = 0;
		do{
			if($store_result = $this->store_result()){
				while ($row = $this->fetch_row($store_result)){
					$result[$k][] = $row;
				}
				$this->free();
			}
			if ($this->more_results()) {
				$k++;
			}
		}while($this->next_result());
		return $result;
	}
	/**
	 * 列出 MySQL 服务器中所有的数据库.返回资源集,然后用fetch_array获取实际数据
	 * @return Resource/null
	 */
	public function list_dbs(){
		//return $this->connect() ? mysql_list_dbs( $this->mysqli) : null;
	}
	/**
	 * 列出 MySQL 进程
	 * @return Resource
	 */
	public function list_processes(){
		//return $this->connect() ? mysql_list_processes( $this->mysqli) : array();
	}
	/**
	 * 取得结果集中字段的数目
	 * @return int
	 */
	public function num_fields( $result){
		return (int)$this->mysqli_result->field_count;
	}
	/**
	 * 取得结果集中行的数目
	 * @return int
	 */
	public function num_rows( $result){
		return (int)$this->mysqli_result->num_rows;
	}
	/**
	 * @var int $option MYSQLI_OPT_CONNECT_TIMEOUT/MYSQLI_OPT_LOCAL_INFILE/MYSQLI_INIT_COMMAND/MYSQLI_READ_DEFAULT_FILE/MYSQLI_READ_DEFAULT_GROUP
	 *                  MYSQLI_SERVER_PUBLIC_KEY/MYSQLI_OPT_NET_CMD_BUFFER_SIZE/MYSQLI_OPT_NET_READ_BUFFER_SIZE
	 * @return boolean
	 */
	public function options($option, $value){
		return (bool)$this->call(array('mysqli', 'options'), array((int)$option, $value));
	}
	/**
	 * Ping 一个服务器连接，如果没有连接则重新连接 wait timeout
	 */
	public function ping(){
		return (bool)$this->call(array('mysqli', 'ping'), array());
	}

	/**
	 * @var int $flags MYSQLI_CLIENT_COMPRESS/MYSQLI_CLIENT_FOUND_ROWS/MYSQLI_CLIENT_IGNORE_SPACE/MYSQLI_CLIENT_INTERACTIVE/MYSQLI_CLIENT_SSL
	 * @return
	 */
	public function real_connect($host,$username,$passwd,$dbname,$port,$socket=null,$flags=null){
		$this->host = $host;
		$this->username = $username;
		$this->passwd = $passwd;
		$this->dbname = $dbname;
		$this->port = $port;
		$this->socket = $socket;

		return (bool)$this->call(array('mysqli', 'real_connect'), array($this->host,$this->username,$this->passwd,$this->dbname,$this->port,$this->socket,(int)$flags));
	}
	/**
	 * 转义 SQL 语句中使用的字符串中的特殊字符，并考虑到连接的当前字符集
	 * @return String
	 */
	public function real_escape_string($unescaped_string){
		return $this->connect() ? (string)$this->call(array('mysqli', 'real_escape_string'), array((string)$unescaped_string)) : $this->escape_string($unescaped_string);
	}
	/**
	 * 返回结果集中一个单元的内容.$row 0,$field 0表示结果集第0条第0个单元格的数据.不能是unbuffered_query
	 * @return Mixed
	 */
	public function result($result, $row, $field=null){
		//return $this->connect ? mysql_result($result, $row, $field) : null;
	}
	/**
	 * 选择 MySQL 数据库.
	 * @return Boolean 没有连接上或者不存在的db
	 */
	public function select_db( $database_name){
		return $this->connect() && (bool)$this->call(array('mysqli', 'select_db'), array((string)$database_name));
	}
	/**
	 * 设置客户端字符集
	 * @return Boolean
	 */
	public function set_charset( $charset){
		return $this->connect() && (bool)$this->call(array('mysqli', 'set_charset'), array((string)$charset));
	}
	/**
	 * @return string
	 */
	public function sqlstate(){
		return (string)$this->mysqli->sqlstate;
	}
	/**
	 * @return boolean
	 */
	public function ssl_set($key, $cert, $ca, $capath, $cipher){
		return (bool)$this->call(array('mysqli', 'ssl_set'), array($key, $cert, $ca, $capath, $cipher));
	}
	/**
	 * 取得当前系统状态
	 * @return String
	 */
	public function stat(){
		return $this->connect() ? (string)$this->call(array('mysqli', 'stat'), array()) : '';
	}
	/**
	 * @var int $option MYSQLI_STORE_RESULT_COPY_DATA
	 * @return xmysqli
	 */
	public function store_result($option=0){
		if(($ret = $this->call(array('mysqli', 'store_result'), array((int)$option))) instanceof mysqli_result){
			$this->mysqli_result = $ret;
			return $this;
		}
		return (bool)$ret;
	}
	/**
	 * 返回当前线程的 ID
	 * @return int
	 */
	public function thread_id(){
		return $this->connect() ? (int)$this->mysqli->thread_id : '';
	}
	/**
	 * @return boolean
	 */
	public function thread_safe(){
		return (bool)$this->call(array('mysqli', 'thread_safe'), array());
	}
	/**
	 * 发送一条 MySQL 查询
	 * @return Boolean/Resource 针对 SELECT，SHOW，DESCRIBE 或 EXPLAIN 语句返回资源集,其他为Boolean
	 */
	public function query( $query, $resultmode=MYSQLI_STORE_RESULT){
		$this->exec_sql = $query;
		$this->connect();
		$this->mysqli_result = null;
		if(($ret = $this->call(array('mysqli', 'query'), array((string)$query, (int)$resultmode))) instanceof mysqli_result){
			$this->mysqli_result = $ret;
			return $this;
		}
		return (bool)$ret;
	}
	/**
	 * 向 MySQL 发送一条 SQL 查询,并不获取和缓存结果的行.
	 * 返回的结果集之上不能使用 mysql_num_rows() 和 mysql_data_seek()。此外在向 MySQL 发送一条新的 SQL 查询之前，必须提取掉所有未缓存的 SQL 查询所产生的结果行。
	 * @return Boolean/Resource 不是一个SELECT查询或者连接失败.不管是不是SELECT,对应的语句都会被执行
	 * http://www.hackingwithphp.com/9/4/9/unbuffered-queries-for-large-data-sets-mysqli_use_result
	 */
	public function unbuffered_query($query){
		$this->exec_sql = $query;
		$this->connect();
		$this->mysqli_result = null;
		if(($ret = $this->call(array('mysqli', 'query'), array((string)$query, MYSQLI_USE_RESULT))) instanceof mysqli_result){
			$this->mysqli_result = $ret;
			return $this;
		}
		return (bool)$ret;
	}
	/**
	 * @return boolean
	 */
	public function real_query($query){
		return (bool)$this->call(array('mysqli', 'real_query'), array($query));
	}
	/**
	 * @return xmysqli
	 */
	public function use_result(){
		if(($ret = $this->call(array('mysqli', 'use_result'), array())) instanceof mysqli_result){
			$this->mysqli_result = $ret;
			return $this;
		}
		return (bool)$ret;
	}
	/**
	 * @return int
	 */
	public function warning_count(){
		return (int)$this->mysqli->warning_count;
	}
	/**
	 * @return xmysqli
	 */
	public function reap_async_query(){
		if(($ret = $this->call(array('mysqli', 'reap_async_query'), array())) instanceof mysqli_result){
			$this->mysqli_result = $ret;
			return $this;
		}
		return (bool)$ret;
	}
	/**
	 * @var int $options MYSQLI_REFRESH_*
	 * @return boolean
	 */
	public function refresh($options){
		return (bool)$this->call(array('mysqli', 'refresh'), array((int)$options));
	}
	/**
	 * @return boolean
	 */
	public function release_savepoint($name){
		return (bool)$this->call(array('mysqli', 'release_savepoint'), array((string)$name));
	}
	/**
	 * @return boolean
	 */
	public function savepoint($name){
		return (bool)$this->call(array('mysqli', 'savepoint'), array((string)$name));
	}
	/**
	 * 获取所有结果
	 * @return Array
	 */
	public function getAll($query, $result_type=MYSQLI_ASSOC){
		$this->unbuffered_query($query);
		while($array = $this->fetch_array($this->mysqli_result, $result_type)) {
			$ret[] = $array;
		}
		$this->free();
		return (array)$ret;
	}
	/**
	 * 获取一行结果
	 * @return Array
	 */
	public function getOne($query, $result_type=MYSQLI_ASSOC){
		$this->unbuffered_query($query);
		$result = $this->fetch_array($this->mysqli_result, $result_type);
		$this->free();
		return $result;
	}

	/**
	 * 事务处理章节
	 */

	//开始一个事务，mode=true自动提交，false=Commit调用提交，关闭自动提交
	public function autocommit($mode=false){
		return (bool)$this->call(array('mysqli', 'autocommit'), array((bool)$mode));
	}
	public function Commit(){
		return (bool)$this->call(array('mysqli', 'commit'), array());
	}
	public function Rollback(){
		return (bool)$this->call(array('mysqli', 'rollback'), array());
	}
	/**
	 * @var int $flags MYSQLI_TRANS_START_READ_ONLY/MYSQLI_TRANS_START_READ_WRITE/MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT
	 * @var string $name
	 * @return boolean
	 */
	public function begin_transaction($flags=null, $name=null){
		return (bool)$this->call(array('mysqli', 'begin_transaction'), $name ? array($flags, (string)$name) : array($flags));
	}
	/**
	 * @return boolean
	 */
	public function change_user($user, $password='', $database=''){
		return (bool)$this->call(array('mysqli', 'change_user'), array((string)$user, (string)$password, (string)$database));
	}
	/**
	 * @return int
	 */
	public function connect_errno(){
		return (int)mysqli_connect_errno();
	}
	/**
	 * @return string
	 */
	public function connect_error(){
		return (string)mysqli_connect_error();
	}
	/**
	 * @return bool
	 */
	public function debug($message){
		return (bool)$this->call(array('mysqli', 'debug'), array((string)$message));
	}
	/**
	 * @return bool
	 */
	public function dump_debug_info(){
		return (bool)$this->call(array('mysqli', 'dump_debug_info'), array());
	}
	public function get_links_stats(){
		return mysqli_get_links_stats();
	}
	public function get_client_stats(){
		return mysqli_get_client_stats();
	}
	/**
	 * @param int $flags
	 * @return boolean
	 */
	public function report($flags){
		return (bool)mysqli_report((int)$flags);
	}
	/**
	 * @return array
	 */
	public function error_list(){
		return (array)$this->mysqli->error_list;
	}
	public function __destruct(){

	}

	/**
	 * N秒内重连
	 * @param array $func
	 * @param array $args
	 */
	private function call($func=array(), $args=array()){
		do{
			$this->connect() && ($ret = @call_user_func_array(array($this->{$func[0]}, $func[1]), $args));
		}while (($this->mysqli->errno == 2006) && ($this->connectlast < time()-60) && $this->close());

		$this->mysqli->errno && $this->errorlog(var_export($func, true)."\n".$this->mysqli->errno.':'.$this->mysqli->error."\n".var_export($args, true), $this->die);

		return $ret;
	}

	/**
	 * 记录错误,加上断线自动重连
	 * 连接不上会强制退出脚本.
	 * 其他则依照$this->die判断..
	 */
	private function errorlog($msg, $die=false){

	}
}