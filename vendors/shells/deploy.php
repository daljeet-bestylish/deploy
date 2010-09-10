<?php
/**
  * Deploy Shell for AudiologyOnline Apps
  * 
  * For deploy to work you must have ssh2 installed on your machine
  * 
  * $ sudo apt-get install libssh2-1-dev libssh2-php
  * OR
  * http://pecl.php.net/package/ssh2
  * 
  * Verify you have ssh2 installed by:
  * $ php -m | grep 'ssh2'
  * You should see 'ssh2', if so, you're good to go!
  */
class DeployShell extends Shell {
  
  /**
    * Deployable apps, as tasks
    */
  var $tasks = array('Hh');
  
  /**
    * environment verbs
    */
  var $environments = array(
    'prod'        => 'prod',
    'production'  => 'prod',
    'dev'         => 'dev',
    'development' => 'dev',
  );
  
  /**
    * Current environment to deploy to
    */
  var $environment = null;
  
  /**
    * Current tag to deploy
    */
  var $tag = null;
  
  /**
    * Show output as it happens
    */
  var $verbose = false;
  
  /**
    * Path to work in
    */
  var $path = null;
  
  /**
    * Default action.
    */
  function main(){
    $this->out("Deploy the parent app to production");
    $this->hr();
    $this->help();
  }
  
  /**
    * Show the help menu
    */
  function help(){
    $this->out("Deploy Help");
    $this->hr();
    $this->out("Usage: cake deploy <app> <environment> <tag>");
    $this->out("Examples:");
    $this->out("  cake deploy hh prod v1.2       Deploy HealthyHearing tag v1.2 to production");
  }
  
  /**
    * This is called from within each task to parse the args
    * - parse the args
    * - verify the tag
    */
  function beforeDeploy(){
    if(empty($this->args)){
      $this->__errorAndExit('No arguments passed, please specify an environment and tag');
    }
    
    //Get the environment
    $environment_key = array_shift($this->args);
    if(!$environment_key){
      $this->__errorAndExit('Please specify an environment.');
    }
    if(isset($this->environments[$environment_key])){
      $this->environment = $this->environments[$environment_key];
    }
    else {
      $this->__errorAndExit("$environment_key is not a valid environment to deploy to.");
    }
    
    //Get the Tag
    $this->tag = array_shift($this->args);
    if(!$this->tag){
      $this->__errorAndExit('Please specify a tag.');
    }
    
    //Get verbose
    $this->verbose = array_shift($this->args);
    
    if(!$this->verifyTag()){
      $this->__errorAndExit("{$this->tag} is not a valid tag.");
    }
  }
  
  /**
    * Verify the tag exists.
    */
  function verifyTag(){
    return ($this->tag == trim(shell_exec("git tag | grep '{$this->tag}'")));
  }
  
  /**
    * Connect and authenticate to an ssh server
    */
  function ssh_open($server, $user, $pass, $port = 22){
    if(!function_exists("ssh2_connect")){
      $this->__errorAndExit("function ssh2_connect doesn't exit.  Run sudo apt-get install libssh2-1-dev libssh2-php");
    }
    
    $this->connection = ssh2_connect($server, $port);
    
    if(!$this->connection){
      $this->__errorAndExit("Unable to connect to $server");
    }
    
    if(!ssh2_auth_password($this->connection, $user, $pass)){
      $this->__errorAndExit("Failed to authenticate");
    }
  }
  
  /**
    * Send and receive the result of an ssh command
    * @param string command to execute on remote server
    * @param array of options
    * - error: default false, if true, show stderr instead of stdout
    * - verbose: default true, shows output as it happens.
    * @param boolean get stderr instead of stdout stream
    * @return mixed result of command.
    */
  function ssh_exec($cmd, $options = array(), $error = false){
    if(!$this->connection){
      $this->__errorAndExit("No open connection detected.");
    }
    
    if($this->path){
      $cmd = "cd {$this->path} && $cmd";
    }
    
    $stream = ssh2_exec($this->connection, $cmd);
    
    if(!$stream){
      $this->__errorAndExit("Unable to execute command $cmd");
    }
    
    $errorStream = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);
    
    stream_set_blocking($stream, true);
    stream_set_blocking($errorStream, true);
    
    $retval = $error ? stream_get_contents($errorStream) : stream_get_contents($stream);
    $retval = trim($retval);
    
    fclose($stream);
    fclose($errorStream);
    
    //Show output or at least progress dots.
    if($this->verbose){
      $this->out($retval);
    }
    else {
      echo '.';
    }
    
    return $retval;
  }
  
  /**
    * Set the path to append to each command.
    * @param string path (without cd)
    */
  function ssh_setpath($path){
    $this->path = $path;
  }
  
  /**
    * Close the current connection
    */
  function ssh_close(){
    if($this->connection){
      $this->ssh_exec("exit");
    }
    unset($this->connection);
  }
  
  
  /**
    * Private method to output the error and exit(1)
    * @param string message to output
    */
  function __errorAndExit($message){
    $this->out("Error: $message");
    exit(1);
  }
}
?>