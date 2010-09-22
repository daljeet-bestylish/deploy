<?php
/**
  * Logic to deploy HealthyHearing app to dev or production
  */
class HhTask extends DeployShell {
  
  /**
    * Name of the app to deploy
    */
  var $name = "Healthy Hearing";
  
  /**
    * Git repository that will be deployed
    */
  var $git = "git@github.com:AudiologyHoldings/HH.git";
  
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
      'hhwww1.healthyhearing.com',
      'hhwww2.healthyhearing.com',
    );
    $user = 'cakedeployer';
    $pass = 'hHd3P10y';
    
    $root_user = 'root';
    $root_pass = '1qaz@WSX3ed';
    
    foreach($servers as $server){
      $this->ssh_open($server,$root_user,$root_pass);
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
    $this->ssh_setpath("/var/www/$path/hh");
    
    $git = $this->ssh_exec("git status");
    if(!$git){
      $this->ssh_setpath("/var/www/$path");
      $this->ssh_exec("git clone {$this->git} hh");
      $this->ssh_exec("mkdir /var/www/$path/hh/app/tmp/cache/views");
      $this->ssh_setpath("/var/www/$path/hh/app/tmp");
      $this->ssh_exec("chmod 777 -R .");
      $this->ssh_exec("mkdir /var/www/$path/hh/app/webroot/static");
      $this->ssh_setpath("/var/www/$path/hh/app/webroot");
      $this->ssh_exec("chmod 777 -R static");
      $this->ssh_setpath("/var/www/$path/hh");
    }
        
    //Update the repository
    $this->ssh_exec("git pull origin master");
    $this->ssh_exec("git fetch --tags");
    $this->ssh_exec("git submodule init");
    
    //Now checkout the tag we wanted.
    $this->ssh_exec("git checkout {$this->tag}");
        
    //Updated submodules to match checked out tag
    $this->ssh_exec("git submodule update");
    
    //Give it to cakedeployer
    $this->ssh_exec("chown -R cakedeployer:cakedeployer .");
    
    //Finally clear the cache
    $this->ssh_exec("./app/scripts/clear_cache");
    
    //Only run migrations once
    if($server == 'hhwww1.healthyhearing.com'){
      $this->ssh_setpath("/var/www/$path/hh/app");
      $this->ssh_exec("./cake migration run all");
    }
  }
  
}
?>