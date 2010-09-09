<?php
/**
  * Deploy Shell
  * 
  * For deploy to work you must have ssh2 installed on your machine
  * 
  * $ sudo apt-get install libssh2-1-dev libssh2-php
  * $ php -m | grep 'ssh2'
  *
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
    
    if(!$this->verifyTag()){
      $this->__errorAndExit("{$this->tag} is not a valid tag.");
    }
  }
  
  /**
    * Verify the tag exists.
    */
  function verifyTag(){
    return ($this->tag == shell_exec("git tag | sort -n | tail -1"));
  }
  
  /**
    * Connect and authenticate to an ssh server
    */
  function ssh_connect(){
    
  }
  
  /**
    * Send and receive the result of an ssh command
    * @param string command to execute on remote server
    * @return mixed result of command.
    */
  function ssh_exec($cmd){
    debug($cmd);
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