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
    
    foreach($servers as $server){
      $this->ssh_open($server,$user,$pass);
      $this->deployLogic();
      $this->ssh_close();
    }
    
    $this->out();
    $this->out("Deploy Finished");
  }
  
  /**
    * Helper deploy logic for each server we're deploying too
    */
  function deployLogic(){
    $path = $this->environment;
    $path = 'deploy'; //For testing
    
    $this->ssh_exec("mkdir /var/www/$path");
    $this->ssh_setpath("/var/www/$path/hh");
    
    $git = $this->ssh_exec("git status");
    if(!$git){
      $this->ssh_setpath("/var/www/$path");
      $this->ssh_exec("git clone {$this->git} hh");
      $this->ssh_exec("mkdir /var/www/$path/hh/app/tmp/cache");
      $this->ssh_setpath("/var/www/$path/hh/app/tmp");
      $this->ssh_exec("chmod 777 -R .");
      $this->ssh_exec("mkdir /var/www/$path/hh/app/webroot/static");
      $this->ssh_setpath("/var/www/$path/hh/app/webroot");
      $this->ssh_exec("chmod 777 -R static");
      $this->ssh_setpath("/var/www/$path/hh");
    }
    
    //Update the repository
    $this->ssh_exec("git pull origin master");
    $this->ssh_exec("git submodule init");
    $this->ssh_exec("git submodule update");
    
    //Now checkout the tag we wanted.
    $this->ssh_exec("git checkout {$this->tag}");
    
    //TODO run migration plugin
    //$this->ssh_exec("cd app && ./cake migration run all");
  }
  
}
?>