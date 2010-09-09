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
    $this->migrate();
  }
  
  /**
    * TODO, fill in this function to run any logic that this app needs
    * to deploy the specific tag
    */
  function deploy(){
    $result = shell_exec("ls -la");
    debug($result);
  }
  
  /**
    * TODO, fill in this function to run any logic that this app needs
    * to migrate the database.
    */
  function migrate(){
  }
  
  
}
?>