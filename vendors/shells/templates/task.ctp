<?php
/**
 * Generate a deploy task for a new app.
 */
echo "<?php\n";
?>
class <?php echo $class; ?>Task extends DeployShell {

  /**
    * Name of the app to deploy
    */
  var $name = "<?php echo $name ?>"; //Human Readable Name of App

  /**
    * Entry point of app.
    */
  function execute(){
    $this->out("Deploying {$this->name}...");
    $this->beforeDeploy();
    $this->deploy();
  }
  
  /**
    * Fill in this function to run any logic that this app needs
    * to deploy the specific tag
    */
  function deploy(){
    //connect to the server you want to deploy to
    $this->ssh_open('server.example.com', 'username', 'password');
    
    //set the path you want to execute commands in
    $this->ssh_setpath("/var/www/{$this->environment}");
    
    //run various commands to acheive desired results
    $result = $this->ssh_exec("git pull origin master && checkout {$this->tag}");
    
    //close the connection
    $this->ssh_close();
    
    $this->out();
    
    //Display results
    if($result){
      $this->out("Deploy Finished");
    }
    else {
      $this->out("Error deploying.");
    }
  }
  
}
<?php echo "?>"; ?>