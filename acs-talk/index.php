<?php

$file = $_GET['file'];

if(isset($file)){
    include("pages/$file");
}else{
    header("Location: index.php?file=home.html");
}
?>