<?php
echo("<div style='margin: 10px'>");
echo("<h3>User Preferences</h3>");

include("includes/update-preferences.inc.php");


echo("<div style='width: 800px; background-color: #fff; padding:5px; margin-bottom:10px; float:left;'>");
echo("</div>");


echo("<div style='width: 300px; float: right;'>");
echo("<div style='background-color: #e5e5e5; border: solid #e5e5e5 10px; margin-bottom:10px;'>");
echo("<h2>Change Password</h2>");

echo($password_message);

echo("<form method='post' action='?page=preferences'><input type=hidden name='action' value='changepass'>
        <table>
        <tr><td>Old Password</td><td><input type=password name=old_pass></input></td></tr>
        <tr><td>New Password</td><td><input type=password name=new_pass></input></td></tr>
        <tr><td>New Password</td><td><input type=password name=new_pass2></input></td></tr>
        <tr><td></td><td align=right><input type=submit></td></tr></table></form>");

echo("</div>");


echo("<div style='background-color: #e5e5e5; border: solid #e5e5e5 10px;  margin-bottom:10px;'>");


echo("<h3>Device Permissions</h3>");

if($_SESSION['userlevel'] == '10') { echo("<strong>Global Administrative Access</strong>"); }
if($_SESSION['userlevel'] == '5')  { echo("<strong>Global Viewing Access</strong>"); }
if($_SESSION['userlevel'] == '1') {

  $perms = mysql_query("SELECT * FROM `devices_perms` AS P, `devices` AS D WHERE `user_id` = '" . $user_id . "' AND P.device_id = D.device_id");


  while($perm = mysql_fetch_array($perms)) {
    echo("<a href='?page=device&id=" . $perm['device_id'] . "'>" . $perm['hostname'] . "</a><br />");
    $dev_access = 1;
  }
  if(!$dev_access) { echo("No access!"); }

 }


echo("</div>");
echo("</div>");


echo("</div>");
?>

