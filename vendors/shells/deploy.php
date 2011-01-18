<?php
/**
  * Deploy Shell for AudiologyOnline Apps
  *  
  *  Example: cake deploy hh prod v1.0.1
  *
  * @version 1.0
  * @author Nick Baker <nick@audiologyonline.com>
  ***************************************
  * Dependancies: ssh2
  * 
  * $ sudo apt-get install libssh2-php
  *
  * OR
  *
  * http://pecl.php.net/package/ssh2
  *************************************** 
  * Verify you have ssh2 installed by:
  *
  * $ php -m | grep 'ssh2'
  *
  * You should see 'ssh2', if so, you're good to go!
  */
App::import('Core','Folder');
class DeployShell extends Shell {
  
  /**
    * Deployable apps, as tasks, this is dynamically loaded
    */
  var $tasks = array();
  
  /**
    * environment verbs to directory names
    */
  var $environments = array(
    'prod'        => 'prod',
    'production'  => 'prod',
    'test'  => 'deploy',
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
    * Dynamically load all tasks that are in the deploy/shell/tasks directory
    */
  function initialize(){
    $this->Folder = new Folder();
    $this->Folder->cd(dirname(__FILE__) . DS . 'tasks');
    foreach($this->Folder->find() as $task){
      $this->tasks[] = Inflector::camelize(str_replace(".php", "", $task));
    }
    parent::initialize();
  }
  
  /**
    * Default action.
    * @return void
    */
  function main(){
    $this->out("Deploy the parent app to production");
    $this->hr();
    $this->help();
  }
  
  /**
    * Show the help menu
    * @return void
    */
  function help(){
    $this->out("Deploy Help");
    $this->hr();
    $this->out("Usage: cake deploy <app> <environment> <tag>");
    $this->out("Usage: cake deploy generate <app>");
    $this->out("Examples:");
    $this->out("  cake deploy hh prod v1.2       Deploy HealthyHearing tag v1.2 to production");
    $this->out("  cake deploy hh dev v1.2        Deploy HealthyHearing tag v1.2 to development");
    $this->out("  cake deploy generate ao        Generate the AoTask to deploy the Ao App.");
    $this->out("  cake deploy tags               List the tags (git shortcut).");
    $this->out("  cake deploy tag                Create the new tag, auto assigns tag (git shortcut).");
    $this->out("  cake deploy tag v1.2           Create the new tag (git shortcut).");
    $this->out("  cake deploy delete_tag v1.2    Deletes local and remote copy of tag (git shortcut).");
  }
  
  /**
    * Genearte a new app deploy task.
    * @return void
    */
  function generate(){
    $app_name = array_shift($this->args);
    if(!$app_name){
      $this->__errorAndExit("Please specify an app name to generate.  cake deploy generate <short_task_name>");
    }
    
    $class = Inflector::classify($app_name);
    $content = $this->__generateTemplate('task', array('class' => $class, 'name' => $app_name));
    $file_name = dirname(__FILE__) . DS . 'tasks' . DS . Inflector::underscore($class) . '.php';
    
    
    //Write the file
    if(file_exists($file_name)){
      $this->__errorAndExit("$app_name deploy task already exists.");
    }
    else {
      $File = new File($file_name, true);
      if($File->write($content)){
        $this->out("$class generated.");
      }
      else {
        $this->__errorAndExit("problem generating file, check permissions.");
      }
    }
  }
  
  /**
  * Delete the inputed tag from local and remote
  */
  function delete_tag(){
  	if(empty($this->args)){
  		$this->out("No tag specified to delete.");
  		$this->tags();
  		exit(1);
  	}
  	
  	$this->tag = array_pop($this->args);
  	if(!$this->verifyTag()){
  		$this->out("{$this->tag} does not exist.");
  		$this->tags();
  		exit(1);
  	}
  	
  	switch($this->promptYesNo("Are you sure you want to delete {$this->tag }?")){
  		case 'y':
  			$this->out(shell_exec("git tag -d {$this->tag}"));
  			$this->out(shell_exec("git push origin :{$this->tag}"));
  			break;
  	}
  }
  
  /**
  * Lists existing tags
  */
  function tags(){
  	  $this->out("Existing Tags:");  
  	  $this->out(trim(shell_exec("git tag -ln")));
  	  $this->out();
  }
  /**
  * create a new tag
  */
  function tag(){
  	if (empty($this->args)) {
  		// nothing entered... lets prompt for auto-tag completion
  		$this->tags();
  		$lastTag = array_pop(explode("\n", trim(shell_exec("git tag"))));
  		if (!empty($lastTag)) {
  			$lastTagParts = explode(".", $lastTag);
  			$lastTagSuffix = array_pop($lastTagParts);
  			if (is_numeric($lastTagSuffix)) {
  				$lastTagSuffix++;
  				$newTag = implode('.', $lastTagParts).".{$lastTagSuffix}";  
  			}
  			//Suggest tag
  			switch($this->promptYesNo("Would you like this as your tag: $newTag")){
  				case 'q':
  					$this->out("bye");
  					exit(1);
  				case 'y':
  					$tag = $newTag;
  					$this->out("tag assinged: {$tag}");
  					break;
  			}
  		}
  	} 
  	else {
  		$tag = trim(array_shift($this->args));
  		$message = empty($this->args) ? "Deploy Script Added Tag {$tag}" : array_shift($this->args);
  	}
  	while (empty($tag)) {
  		$tag = trim($this->in("Enter your tag"));
  	}
  	if (!empty($tag)) {
  		//Are you sure this is your tag?
  		switch($this->promptYesNo("Are you sure this is your tag with message: $tag '$message'")){
  			case 'n':
  				$this->args = array();
  				return $this->tag();
  			case 'q':
  				$this->out('bye');
  				exit(1);
  		}
  	}
  	$this->out(shell_exec("git tag -a '{$tag}' -m '$message'"));
  	
  	//Push tags?
  	switch($this->promptYesNo("Want to push your tags?")){
  		case 'y':
  			$this->out(shell_exec("git push --tags"));
  			break;
  	}
  }
  
  /**
  	* Prompt the user with a yes no question
  	* @param string text
  	* @param string default answer
  	* @return result strlowered
  	*/
  private function promptYesNo($text, $default = "Y"){
  	return trim(strtolower($this->in($text,array('Y','n','q'), $default)));
  }
  
  /**
    * This is called from within each task to parse the args
    * - parse the args
    * - verify the tag
    * @return void
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
    * @return boolean true if the tag exists in the current git repository
    */
  function verifyTag(){
    return ($this->tag == trim(shell_exec("git tag | grep '{$this->tag}'")));
  }
  
  /**
    * Connect and authenticate to an ssh server
    * @param string server to connec to
    * @param string user to use to conenct to server with
    * @param string password to use to conenct to server with
    * @param string port to use to conenct to server with
    * @return void
    */
  function ssh_open($server, $user, $pass, $port = 22){
    if(!function_exists("ssh2_connect")){
      $this->__errorAndExit("function ssh2_connect doesn't exit.  Run sudo apt-get install libssh2-1-dev libssh2-php");
    }
    
    if($server == 'server.example.com'){
      $this->__errorAndExit("Please fill in the deploy() function in your new app task");
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
    * @param boolean get stderr instead of stdout stream
    * @return mixed result of command.
    */
  function ssh_exec($cmd, $error = false){
    if(!$this->connection){
      $this->__errorAndExit("No open connection detected.");
    }
    
    if($this->path){
      $cmd = "cd {$this->path} && $cmd";
    }
    if($this->verbose){
      $this->out($cmd);
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
    * @return void
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
    $this->connection = null;
  }
  
 /**
   * Include and generate a template string based on a template file
   *
   * @param string $template Template file name
   * @param array $vars List of variables to be used on tempalte
   * @return string
   * @access private
   */
 function __generateTemplate($template, $vars) {
   extract($vars);
   ob_start();
   ob_implicit_flush(0);
   include(dirname(__FILE__) . DS . 'templates' . DS . $template . '.ctp');
   $content = ob_get_clean();
   return $content;
 }
  
  
  /**
    * Private method to output the error and exit(1)
    * @param string message to output
    * @return void
    * @access private
    */
  function __errorAndExit($message){
    $this->out("Error: $message");
    exit(1);
  }
}
?>