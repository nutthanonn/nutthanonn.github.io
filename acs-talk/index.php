<!DOCTYPE html>
<html>
    <link rel="stylesheet" href="style.css">
<head>
    <title>Ping Pong Enterprise</title>
</head>
<body>
<div id='box'>
    <form id="myForm" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
        <label for="inputString">Enter a url to test connection: </label>
        <input type="text" id="inputString" name="url">
        <input type="submit" value="Submit">
    </form>
    
    <?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $value = $_POST['url'] ?? '';

    if (strpos($value, ' ') !== false) {
        echo "<p>URL cannot contain spaces</p>";
    }else{
        if (!empty($value)) {
            $ping = "ping -c 1 " . $value;
            $result = shell_exec($ping);
            
            echo "<p><b>Ping result</b> $result</p>";
        } else {
            echo "<p>Please enter a valid URL</p>";
        }
    }
}
?>
</div>
<div class='ping-pong'></div>
<script>
function submitForm() {
    document.getElementById("myForm").submit();
}
</script>
</body>
</html>
