<?php
/**
* Logic to deploy HealthyHearing app to dev or production
*/
class SpTask extends DeployShell {

	/**
    * Name of the app to deploy
    */
    var $name = "Speech Pathology";

    /**
    * Git repository that will be deployed
    */
    var $git = "git@github.com:AudiologyHoldings/SP.git";

    /**
    * Entry point of app.
    */
    function execute(){
    	$this->out("Deploying {$this->name}...");
    	$this->beforeDeploy();
    	$this->deploy();
    }

    /**
    * fill in this function to run any logic that this app needs
    * to deploy the specific tag
    */
    function deploy(){
    	$servers = array(
    		'www1.speechpathology.com',
    		'www2.speechpathology.com',
    		);
    	$user = 'cakedeployer';
    	$pass = 'hHd3P10y';

    	foreach($servers as $server){
    		$this->ssh_open($server, $user, $pass);
    		$this->deployLogic($server);
    		$this->ssh_close();
    	}

    	$this->out();
    	$this->out("Deploy Finished");
    }

    /**
    * Helper deploy logic for each server we're deploying too
    */
    function deployLogic($server){
    	$path = $this->environment;

    	$this->ssh_exec("mkdir /var/www/$path");
    	$this->ssh_setpath("/var/www/$path/sp");

    	$git = $this->ssh_exec("git status");
    	if(!$git){
    		$this->ssh_setpath("/var/www/$path");
    		$this->ssh_setpath("rmdir /var/www/$path/sp");
    		$this->ssh_exec("git clone {$this->git} sp");
    		$this->ssh_exec("mkdir /var/www/$path/sp/app/tmp/cache/views");
    		$this->ssh_exec("mkdir /var/www/$path/sp/app/tmp/cache/models");
    		$this->ssh_exec("mkdir /var/www/$path/sp/app/tmp/cache/persistant");
    		$this->ssh_exec("chmod 777 -R /var/www/$path/sp/app/tmp/");
    		$this->ssh_exec("mkdir /var/www/$path/sp/app/webroot/cache");
    		$this->ssh_exec("mkdir /var/www/$path/sp/app/webroot/cache_js");
    		$this->ssh_exec("mkdir /var/www/$path/sp/app/webroot/cache_css");
    		$this->ssh_exec("chmod 777 -R /var/www/$path/sp/app/webroot/");
    		$this->ssh_setpath("/var/www/$path/sp");
    	}

    	//Update the repository
    	$this->ssh_exec("git checkout master");
    	$this->ssh_exec("git pull");
    	$this->ssh_exec("git fetch --tags");
    	$this->ssh_exec("git submodule init");

    	//Now checkout the tag we wanted.
    	$this->ssh_exec("git checkout {$this->tag}");

    	//Updated submodules to match checked out tag
    	$this->ssh_exec("git submodule update");

    	//Update each local file's timestamp to the time it was last modified in git
    	$this->ssh_exec("/usr/local/bin/git-set-file-times");

    	//Give it to cakedeployer
    	$this->ssh_exec("chown -R cakedeployer:www-data .");

    	//Finally clear the cache
    	$this->ssh_exec("wget 'http://{$server}/caches/clear/all' --delete-after");
    	/*
    	# disabled while we have git
    	//Only run migrations once
    	if($server == 'www1.speechpathology.com'){
    	$this->ssh_setpath("/var/www/$path/sp/app");
    	$this->ssh_exec("HTTP_HOST=prod ./cake migration run all");
    	}
    	*/
    }
}
?>
