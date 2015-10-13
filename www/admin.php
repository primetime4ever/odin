<?php

include_once( "include/usermanagement.php" );
include_once( "include/tablegenerator.php" );

function displayUsers() {
    $usermanagement = new UserManagement();
    $users = $usermanagement->getUsers();

    $tableGenerator = new TableGenerator(); 
    $tableGenerator->addColumn( 'user id', '%d', array( 'usr_id' ) );
    $tableGenerator->addColumn( 'username', '%s', array( 'usr_usern' ) );
    $tableGenerator->addColumn( 'name', '%s %s', array( 'usr_firstn','usr_lastn' ) );
    $tableGenerator->addColumn( 'email', '%s', array( 'usr_email' ) );
    $tableGenerator->addColumn( '', '<a href="admin.php?manage_users=edituser&user_id=%s">edit</a>', array( 'usr_id' ) );
    $tableGenerator->addColumn( '', '<a href="admin.php?manage_users=removeuser&user_id=%s">remove</a>', array( 'usr_id' ) );
    $tableGenerator->setData( $users );
    echo $tableGenerator->generateHTML();
}

function removeUserPage( $userid ) {
    $usermanagement = new UserManagement();
    $userdata = $usermanagement->getUserInfo( $userid );
    $username = $userdata[ 'usr_usern' ];
    $userfirstname = $userdata[ 'usr_firstn' ];
    $userlastname = $userdata[ 'usr_lastn' ];
    $useremail = $userdata[ 'usr_email' ];
    echo '<FORM method="post" action="admin.php?manage_users">
        <INPUT type="hidden" name="rupUserID" value="'.$userid.'">
        <center>Are you sure you want to remove the following user:
        <table>
        <tr><td><b>Username</b></td><td>'.$username.'</td></tr>
        <tr><td><b>Name</b></td><td>'.$userfirstname.' '.$userlastname.'</td></tr>
        <tr><td><b>Email</b></td><td>'.$useremail.'</td></tr>
        <tr><td align="right" colspan=2>
        <BUTTON type="submit" name="rupSubmit" value="Yes">Yes</BUTTON>
        <BUTTON type="submit" name="rupSubmit" value="No">No</BUTTON></td></tr>
        </FORM>';
}

function editUserPage( $action, $userid = "" ) {
    $username = "";
    $userfirstname = "";
    $userlastname = "";
    $useremail = "";

    if ( $action === 'edit' ) {
        $usermanagement = new UserManagement();
        $userdata = $usermanagement->getUserInfo( $userid );
        $username = $userdata[ 'usr_usern' ];
        $userfirstname = $userdata[ 'usr_firstn' ];
        $userlastname = $userdata[ 'usr_lastn' ];
        $useremail = $userdata[ 'usr_email' ];
    }

    echo '<FORM method="post" action="admin.php?manage_users">
        <table>
            <INPUT type="hidden" name="eupUserID" value="'.$userid.'">
            <INPUT type="hidden" name="eupType" value="'.$action.'">
        <tr><td>
            Username:</td><td><INPUT type="text" name="eupUsername" value="'.$username.'">
        </td></tr>
        <tr><td>
            First name:</td><td><INPUT type="text" name="eupFirstname" value="'.$userfirstname.'">
        </td></tr>
        <tr><td>
            Last name:</td><td><INPUT type="text" name="eupLastname" value="'.$userlastname.'">
        </td></tr>
        <tr><td>
            Email:</td><td><INPUT type="text" name="eupEmail" value="'.$useremail.'">
        </td></tr>
        <tr><td>
            Password:</td><td><INPUT type="password" name="eupPassword">
        </td></tr>
        <tr><td align="right" colspan=2>
            <BUTTON type="submit" name="eupSubmit" value="Save">Save</BUTTON>
            <BUTTON type="submit" name="eupSubmit" value="Cancel">Cancel</BUTTON>
        </td></tr>
        </table></FORM>';
}

echo '<html>
        <head>
            <link rel="stylesheet" href="odin.css">
            <script type="text/javascript" src="include/sorttable.js"></script>
        </head>';

echo '<a href="admin.php?manage_users">Manage users</a> <a href="admin.php?manage_networks">Manage networks</a><br><hr>';


/* Submit received from edit user page */
if ( isset( $_POST[ 'eupSubmit' ] ) ) {
    if ( $_POST[ 'eupSubmit' ] === 'Save' ) {
        $usermanagement = new UserManagement();

        /* we need to edit an existing user */
        if ( $_POST[ 'eupType' ] === 'edit' ) {
            $usermanagement->updateUser(
                    $_POST[ 'eupUserID' ],
                    $_POST[ 'eupUsername' ],
                    $_POST[ 'eupPassword' ],
                    $_POST[ 'eupFirstname' ],
                    $_POST[ 'eupLastname' ],
                    $_POST[ 'eupEmail' ] );
        }
        
        /* we're adding a new user */
        if ( $_POST[ 'eupType' ] === 'add' ) {
            $usermanagement->addUser(
                    $_POST[ 'eupUsername' ],
                    $_POST[ 'eupPassword' ],
                    $_POST[ 'eupFirstname' ],
                    $_POST[ 'eupLastname' ],
                    $_POST[ 'eupEmail' ] );
        }

    }
}
/* submit received from remove user page */
if ( isset ( $_POST[ 'rupSubmit' ] ) ) {
    if ( $_POST[ 'rupSubmit' ] === 'Yes' ) {
        $usermanagement = new UserManagement();
        $usermanagement->removeUser( $_POST[ 'rupUserID' ] );
    }
}

if ( isset( $_REQUEST[ 'manage_users' ] ) ) {
    if ( empty( $_REQUEST[ 'manage_users' ] ) ) {
        /* Display a list of our users */
        displayUsers();
        echo '<br><a href="admin.php?manage_users=adduser">Add user</a>';
    }
    
    if ( $_REQUEST[ 'manage_users' ] === 'adduser' ) {
        editUserPage( "add" );
    }

    if ( $_REQUEST[ 'manage_users' ] === 'edituser' ) {
        $user_id = $_REQUEST[ 'user_id' ];
        editUserPage( "edit", $user_id );
    }

    if ( $_REQUEST[ 'manage_users' ] === 'removeuser' ) {
        $user_id = $_REQUEST[ 'user_id' ];
        removeUserPage( $user_id );
    }
}

echo '</html>';

?>
