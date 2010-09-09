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
    $this->ssh_open("hhwww1.healthyhearing.com","cakedeployer","hHd3P10y");
    $this->ssh_exec("mkdir /var/www/deploy");
    $this->ssh_setpath("/var/www/deploy/hh");
    
    $git = $this->ssh_exec("git status");
    if(!$git){
      $this->ssh_exec("git clone {$this->git} hh");
    }
    
    $this->ssh_exec("git submodule init");
    $this->ssh_exec("git submodule update");
    $this->ssh_exec("git checkout {$this->tag}");
    
    //TODO run migration plugin
    
    $this->ssh_close();
    
    $this->out("Deploy Finished");
  }
  
}
?>