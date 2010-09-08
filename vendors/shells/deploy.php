<?php
/**
  * Deploy Shell
  */
class DeployShell extends Shell {
  
  /**
    * Deployable apps, as tasks
    */
  var $tasks = array('Hh');
  
  function main(){
    $this->out("Deploy the parent app to production");
    $this->help();
  }
  
  function help(){
    $this->out("Deploy Help");
    $this->hr();
    $this->out("Usage: cake deploy <app> <environment> <tag>");
    $this->out("Examples:");
    $this->out("  cake deploy hh prod v1.2       Deploy HealthyHearing tag v1.2 to production");
  }
}
?>