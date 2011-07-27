<?php
/**
* Deploy Shell for AudiologyOnline Apps
*  
*  Example: cake deploy app prod v1.0.1
*
* @version 2.0
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
		'prod'        	=> 'prod',
		'production'  	=> 'prod',
		'test'  		=> 'deploy',
		'qa'         	=> 'qa',
		'uat' 			=> 'qa',
		'dev'         	=> 'dev',
		'development' 	=> 'dev',
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
	* Path to application tasks.
	*/
	var $tasksPath = null;
	
	/**
	* Dynamically load all tasks that are in the app/vendors/shell/tasks directory
	*/
	function initialize(){
		$this->tasksPath = APP . 'vendors' .  DS . 'shells' . DS .'tasks' . DS;
		$this->Folder = new Folder();
		$this->Folder->cd($this->tasksPath);
		foreach($this->Folder->find() as $task){
			if($task == 'deploy_logic.php'){
				$this->tasks[] = Inflector::camelize(str_replace(".php", "", $task));
			}
		}
		parent::initialize();
	}
	
	/**
	* Default action.
	* @return void
	*/
	function main(){
		$cmd = array_shift($this->args);
		if (method_exists($this->DeployLogic, $cmd)) {
			$this->out(":App Specific Command: $cmd()");
			$this->out();
			$this->DeployLogic->$cmd();
			$this->out();
		} else {
			$this->help();
		}
	}
	
	/**
	* Run the app task deploy
	*/
	function app(){
		if (isset($this->DeployLogic)){
			$this->DeployLogic->execute();
		} else {
			$this->__errorAndExit("No Deploy Logic detected.  Please generate a deploy script by running\n\n cake deploy generate");
		}
	}
	
	/**
	* Show the help menu
	* @return void
	*/
	function help(){
		$this->out("Deploy Help");
		$this->hr();
		$this->out("Usage: cake deploy app <environment> <tag>");
		$this->out("Usage: cake deploy generate");
		$this->out("Examples:");
		$this->out("  cake deploy app prod v1.2       Deploy 'this application' tag 'v1.2' to the 'production  environment'");
		$this->out("  cake deploy app qa v1.2         Deploy 'this application' tag 'v1.2' to the 'QA environment'");
		$this->out("  cake deploy app dev v1.2        Deploy 'this application' tag 'v1.2' to the 'development environment'");
		$this->out("  cake deploy app dev BRANCH      Deploy 'this application' branch 'BRANCH' to the 'development environment'");
		$this->out("  cake deploy app dev-NAME        Deploy 'this application' branch 'dev-NAME' to the 'dev-NAME' (requires configuration on deploytasks)");
		$this->out("  cake deploy tags                List the tags (git shortcut).");
		$this->out("  cake deploy tags delete         List and promt to to delete for each tag.");
		$this->out("  cake deploy tag                 Create the new tag, auto assigns tag (git shortcut).");
		$this->out("  cake deploy tag v1.2            Create the new tag (git shortcut).");
		$this->out("  cake deploy delete_tag v1.2     Deletes local and remote copy of tag (git shortcut).");
		$this->out("  cake deploy sync_tags           Sync local tags to remote tags (git shortcut).");
		if (method_exists($this->DeployLogic, 'help')) {
			$this->out();
			$this->out("App Specific Help");
			$this->out();
			$this->DeployLogic->help();
			$this->out();
		} else {
			$this->out();
			$this->out("Get Started:");
			$this->out("  cake deploy generate            Generate the DeployLogicTask to deploy the App.");
			$this->out("                                  You will then need to edit and setup your deploy script.");
		}
		$this->tags();
	}
	
	/**
	* Genearte a new app deploy task.
	* @return void
	*/
	function generate(){
		$app_name = 'deploy_logic';
		$class = Inflector::classify($app_name);
		$content = $this->__generateTemplate('task', array('class' => $class, 'name' => $app_name));
		$file_name = $this->tasksPath . Inflector::underscore($class) . '.php';
		
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
	* Go through the local tags, delete them all and then pull in from remote.
	*/
	function sync_tags(){
		$tags = $this->getTagsAsArray();
		if($this->promptContinue("Your local tags will by synced with the remote.  This process is irreversible. Are you sure?")){
			if(!empty($tags)){
				foreach($tags as $tag){
					shell_exec("git tag -d $tag");
				}
			}
			shell_exec("git fetch --tags");
		}
	}
	
	/**
	* Tag sort by tag name
	* @param sortby string (tag|date) tag by default
	* @return output of git command
	*/
	private function git_tags($sortby = 'tag', $show_message = true){
		switch($sortby){
			case 'date':
				if($show_message){
					$command = "git for-each-ref --sort=taggerdate --format='%(refname)\t%(subject)' refs/tags";
				}
				else {
					$command = "git for-each-ref --sort=taggerdate --format='%(refname)' refs/tags";
				}
				break;
			default:
				if($show_message){
					$command = "git tag -ln";
				}
				else {
					$command = "git tag";
				}
				break;
		}
		$output = trim(shell_exec($command));
		$lines = explode("\n", $output);
		foreach ( $lines as $i => $line ) {
			if (strpos($line, 'refs/tags/')!==false) {
				$lines[$i] = str_replace('refs/tags/', '', $line);
			}
		}
		$lines = array_reverse($lines);
		return $lines;
	}
	
	/**
	* Lists existing tags
	*/
	function tags($limit = 10, $sortby = 'date') {
		$lines = $this->git_tags($sortby);
		if (in_array('delete', $this->args)) {
			foreach ( $lines as $line ) {
				$deleteIT = $this->promptYesNo("Delete Tag?: {$line}");
				if ($deleteIT=='y') {
					$parts = explode(' ', str_replace('	', ' ', $line));
					$tag = $parts[0];
					$this->out(shell_exec("git tag -d {$tag}"));
					$this->out(shell_exec("git push origin :{$tag}"));
				}
			}
		} else {
			$total = count($lines);
			if ($total > $limit) {
				$lines = array_slice($lines, 0, $limit);
			}
			foreach ( $lines as $key => $val ) {
				$lines[$key] = "  $val";
			}
			if ($total > count($lines)) {
				$this->out("Existing Tags: (only showing " . count($lines) . "  out of {$total})");
			} else {
				$this->out("Existing Tags: ({$total})");
			}
		}
		$this->out(implode("\n", $lines));
		$this->out();
		$this->out("    cake deploy app prod ____");
		$this->out();
	}
	
	/**
	* create a new tag
	*/
	function tag(){
		if (empty($this->args)) {
			// nothing entered... lets prompt for auto-tag completion
			$this->tags();
			$lines = $this->git_tags('date');
			$lastTagLine = array_shift($lines);
			$parts = explode(' ', str_replace('	', ' ', $lastTagLine));
			$lastTag = $parts[0];
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
		} else {
			$tag = trim(array_shift($this->args));
		}
		while (empty($tag)) {
			$tag = trim($this->in("Enter your tag"));
		}
		if (empty($tag)) {
			$this->out('sorry - invalid tag (empty)');
			exit(1);
		}
		// create message (default)
		$message = empty($this->args) ? "Deploy Script Added Tag {$tag}" : array_shift($this->args);
		$message = $this->in("Enter your message (or 'q' to quit)",'',$message);
		while (empty($message)) {
			$message = $this->in("Enter your message (or 'q' to quit)");
		}
		if ($message=='q') {
			$this->out('bye');
			exit(1);
		}
		//Are you sure this is your tag?
		switch($this->promptYesNo("Are you sure this is your tag with message: $tag '$message'")){
			case 'n':
				$this->hr();
				$this->args = array();
				$this->tag();
				break;
			case 'q':
				$this->out('bye');
				exit(1);
		}
		$this->out(shell_exec("git tag -a '{$tag}' -m '$message'"));
		//Push tags?
		switch($this->promptYesNo("Want to push your tags?")){
			case 'y':
				$this->out(shell_exec("git push --tags"));
				$this->tag = $tag;
				if ($this->promptYesNo("Want to deploy this tag to qa?")=='y') {
					$this->args = array('qa', $tag);
					$this->environment = 'qa';
					$this->app();
				}
				if ($this->promptYesNo("Want to deploy this tag to prod?")=='y') {
					$this->args = array('prod', $tag);
					$this->environment = 'prod';
					$this->app();
				}
				break;
		}
		$this->out();
		$this->out("  cake {$this->shell} app prod {$tag}");
		$this->out("  cake {$this->shell} app qa {$tag}");
		$this->out("  cake {$this->shell} app dev {$tag}");
		$this->out();
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
		if (isset($this->DeployLogicTask->environments)) {
			$this->environments = $this->DeployLogicTask->environments;
		}
		//Get the environment
		$environment_key = array_shift($this->args);
		if (!$environment_key) {
			$this->__errorAndExit('Please specify an environment.');
		} elseif (isset($this->environments[$environment_key])) {
			$this->environment = $this->environments[$environment_key];
		} elseif (in_array($environment_key, $this->environments)) {
			$this->environment = $environment_key;
		} else {
			$this->__errorAndExit("$environment_key is not a valid environment to deploy to.");
		}
		//Get the Tag
		$this->tag = array_shift($this->args);
		if (!$this->tag && substr($this->environment, 0, 4)=='dev-') {
			$this->tag = $this->environment;
		}
		if (!$this->tag) {
			$this->__errorAndExit('Please specify a tag.');
		}
		
		//Get verbose
		$this->verbose = array_shift($this->args);
		
		if(!$this->verifyTag()){
			$this->__errorAndExit("{$this->tag} is not a valid tag.");
		}
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
	* Private method to output the error and exit(1)
	* @param string message to output
	* @return void
	* @access private
	*/
	protected function __errorAndExit($message){
		$this->out("Error: $message");
		exit(1);
	}
	
	/**
	* Return the existing tags as an associative array.
	* @return array of current local tags
	*/
	protected function getTagsAsArray(){
		return explode("\n", trim(shell_exec("git tag -l")));
	}
	
	/**
	* Verify the tag exists.
	* @return boolean true if the tag exists in the current git repository
	*/
	protected function verifyTag(){
		$tagMatches = trim(shell_exec("git tag | grep '{$this->tag}'"));
		if ($tagMatches == $this->tag || preg_match("#\b{$this->tag}\b#", $tagMatches)) {
			return true;
		}
		$branchMatches = trim(trim(trim(shell_exec("git branch -l | grep '{$this->tag}'")),'*'));
		if ($branchMatches == $this->tag || preg_match("#\b{$this->tag}\b#", $branchMatches)) {
			return true;
		}
		return false;
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
	* Prompt to continue, helper method, will exit if answer is n or q, returns true otherwise
	* @param string text to prompt
	*/
	private function promptContinue($text = null){
		switch($this->promptYesNo($text)){
			case 'q':
			case 'n':
				$this->out("Exiting.");
				exit();
		}
		return true;
	}
	
	/**
	* Include and generate a template string based on a template file
	*
	* @param string $template Template file name
	* @param array $vars List of variables to be used on tempalte
	* @return string
	* @access private
	*/
	private function __generateTemplate($template, $vars) {
		extract($vars);
		ob_start();
		ob_implicit_flush(0);
		include(dirname(__FILE__) . DS . 'templates' . DS . $template . '.ctp');
		$content = ob_get_clean();
		return $content;
	}
}
?>