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
    $path = $this->environment;
    $path = 'deploy'; //For testing
    $this->ssh_open("hhwww1.healthyhearing.com","cakedeployer","hHd3P10y");
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
    
    $this->ssh_exec("git checkout master");
    $this->ssh_exec("git pull");
    $this->ssh_exec("git submodule init");
    $this->ssh_exec("git submodule update");
    
    //Now checkout the tag we wanted.
    $this->ssh_exec("git checkout {$this->tag}");
    
    //TODO run migration plugin
    //$this->ssh_exec("cd app && ./cake migration run all");
    
    $this->ssh_close();
    
    $this->out();
    $this->out("Deploy Finished");
  }
  
}
?>