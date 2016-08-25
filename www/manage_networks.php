<?php
 // always include this file first
include_once('include/session.php');

include_once('include/html_frame.php');
include_once('include/nwmanagement.php');
include_once('include/mail_handler.php');

if ($_SESSION['user_data']['usr_privileges'] < 2) {
  header('Location: overview.php');
  exit;
}

$nwManager = new NetworkManagement();

$alert_message = '';
$alert_type = '';

if (isset( $_POST['add_network'] )) {
  if ( $_POST[ 'add_network' ] === 'Add network' ) {
    $nwManager->addNetwork(
        $_POST[ 'nw_base' ],
        $_POST[ 'nw_cidr' ],
        $_POST[ 'nw_description' ]
      );
  }    
}

if (isset( $_POST[ 'delete_and_notify' ] )) {

  $nwId = $_POST[ 'networkId' ];
  $nwInfo = $nwManager->getNetworkInfo( $nwId );
  
  // NOTICE: Mailhandler have to be called before network is removed
  $mail_handler = new MailHandler();
  $mail_handler->notifyNetworkUsersDelete( $nwId, $nwInfo[ 'nw_base' ].'/'.$nwInfo[ 'nw_cidr' ], $_POST['notificationMessage'], $_SESSION['user_data']['usr_id'] );

  $nwManager->removeNetwork($_POST[ 'networkId' ]);

  $alert_message = 'Network <strong>'.$nwInfo[ 'nw_base' ].'/'.$nwInfo[ 'nw_cidr' ].'</strong> was succefully removed.';
  $alert_type = 'success';
}

if ( !empty( $_POST[ 'notify_only' ] ) && !empty( $_POST[ 'notificationMessage' ] ) ) {
  $nwId = $_POST[ 'networkId' ];
  $nwInfo = $nwManager->getNetworkInfo( $nwId );
  $mail_handler = new MailHandler();
  $mail_handler->notifyNetworkUsers( $nwId, $nwInfo[ 'nw_base' ].'/'.$nwInfo[ 'nw_cidr' ], $_POST['notificationMessage'], $_SESSION['user_data']['usr_id'] );

  $alert_message = 'Users of network <strong>'.$nwInfo[ 'nw_base' ].'/'.$nwInfo[ 'nw_cidr' ].'</strong> was messaged about changes.';
  $alert_type = 'success';
}

if (isset( $_POST[ 'edit_description' ] )) {
  $nwManager->updateNetwork($_POST[ 'networkId' ], $_POST[ 'networkDescription' ]);

  $alert_message = 'The description of network <strong>'.$_POST[ 'networkBase2' ].'/'.$_POST[ 'networkCidr2' ].'</strong> was updated.';
  $alert_type = 'success';
}

generate_data();

function generate_data() {
  $nwManager = new NetworkManagement();
  $_SESSION[ 'networks' ] = $nwManager->getNetworks();
}

function generate_nw_list() {
  foreach ( $_SESSION[ 'networks' ] as $row ) {
    echo '
                 <tr>
                    <td>'.$row[ 'nw_base' ].'/'.$row['nw_cidr'].'</td>
                    <td>'.substr($row[ 'nw_description' ], 0, 150).' ...</td>
                    <td><a class="open-EditNetworkDialog" 
                          data-networkid="'.$row[ 'nw_id' ].'" 
                          data-networkbase="'.$row[ 'nw_base' ].'" 
                          data-networkcidr="'.$row[ 'nw_cidr' ].'" 
                          data-networkdescription="'.$row[ 'nw_description' ].'" 
                          href="#editNetworkDialog" data-toggle="modal" 
                          data-backdrop="static"><i class="glyphicon glyphicon-pencil"></i></a></td>
                    <td><a class="open-RemoveNetworkDialog" 
                          data-networkid="'.$row[ 'nw_id' ].'" 
                          data-networkbase="'.$row[ 'nw_base' ].'" 
                          data-networkcidr="'.$row[ 'nw_cidr' ].'" 
                          data-networkdescription="'.$row[ 'nw_description' ].'" 
                          href="#removeNetworkDialog" data-toggle="modal" 
                          data-backdrop="static"><i class="glyphicon glyphicon-trash"></i></a></td>
                  


                  </tr>
    ';
  }
}

$alert_html = '';
if ($alert_message != '' && $alert_type != '') {
  $alert_html = '<div class="alert alert-'.$alert_type.' fade in">
            <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
            '.$alert_message.'
          </div>';
}

$frame = new HTMLframe();
$frame->doc_start("Manage Networks");

echo '
<!-- Modal ADD NETWORK code start -->
    <div class="modal fade" id="addNetworkModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title" id="myModalLabel">Add network</h4>
          </div>
          <form method="POST" action="manage_networks.php">
            <div class="modal-body">
              <div class="form-group">
                <label for="addNetworkBase">Network</label>
                <input type="text" class="form-control" name="nw_base" id="addNetworkBase" placeholder="Network">
              </div>
              <div class="form-group">
                <label for="addCIDR">CIDR/Netmask</label>
                <input type="text" class="form-control" name="nw_cidr" id="addCIDR" placeholder="Write subnet in CIDR or Netmask notation">
              </div>
              <div class="form-group">
                <label for="addNetworkDescription">Network description</label>
                <textarea class="form-control" rows="3" name="nw_description" id="addNetworkDescription" placeholder="Network description"></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
              <input name="add_network" type="submit" value="Add network" class="btn btn-primary">
            </div>
          </form>
        </div>
      </div>
    </div>
<!-- Modal ADD NETWORK code end -->


<!-- Modal EDIT NETWORK code start -->
    <div class="modal fade" id="editNetworkDialog" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title" id="myModalLabel">Edit network</h4>
          </div>
          <form class="form" method="POST" action="manage_networks.php">
            <div class="modal-body">
              <div class="form-group">
                <label for="networkBase">Base</label>
                <input type="text" class="form-control" id="networkBase" name="networkBase" value="" disabled/>
                <input type="hidden" id="networkBase2" name="networkBase2" value=""/>
                <input type="hidden" id="networkId" name="networkId" value=""/>
              </div>
              <div class="form-group">
                <label for="networkCidr">CIDR</label>
                <input type="text" class="form-control" id="networkCidr" name="networkCidr" value="" disabled/>
                <input type="hidden" id="networkCidr2" name="networkCidr2" value=""/>
              </div>
              <div class="form-group">
                <label for="networkDescription">Network description</label>
                <textarea class="form-control" rows="3" id="networkDescription" name="networkDescription" value="" placeholder="Description of network"></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
              <input type="submit" class="btn btn-primary" name="edit_description" value="Save changes"/>
            </div>
          </form>
        </div>
      </div>
    </div>
<!-- Modal EDIT NETWORK code end -->    
    

<!-- Modal DELETE NETWORK code start -->
    <div class="modal fade" id="removeNetworkDialog" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title" id="myModalLabel">Delete network</h4>
          </div>
          <form class="form" action="manage_networks.php" method="POST">
            <div class="modal-body">
            
              <div class="form-group">
                <label for="networkBase">Base</label>
                <input type="text" class="form-control" id="networkBase" name="networkBase" value="" disabled/>
                <input type="hidden" class="form-control" id="networkId" name="networkId" value=""/>
              </div>
              <div class="form-group">
                <label for="networkCidr">CIDR</label>
                <input type="text" class="form-control" id="networkCidr" name="networkCidr" value="" disabled/>
              </div>
              <div class="form-group">
                <p>It is strongly advised to notify current and recent users in conjunction with deleting the network range. Please provide a message below providing information about new available ranges and somewhere to direct questions. Also remember that deleting the range does not actually free the physical hold of addresses users still might have. Have a nice day and so on.</p>
              </div>
              <div class="form-group">
                <label for="notificationMessage">Notification message</label>
                <textarea class="form-control" rows="3" id="notificationMessage" name="notificationMessage" value="" placeholder="Notification message"></textarea>
              </div>
     
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
              <input type="submit" class="btn btn-primary" name="notify_only" value="Notify users"/>
              <input type="submit" class="btn btn-primary" name="delete_and_notify" value="Delete range and notify users"/>
            </div>
          </form>
        </div>
      </div>
    </div>
<!-- Modal DELETE NETWORK code end -->
';

$frame->doc_nav("Networks", $_SESSION[ 'user_data' ][ 'usr_usern' ]);

echo '
    <div class="container">
      <div class="row">
        <div class="col-lg-offset-1 col-lg-8">

        '.$alert_html.'

          <div class="row">
            <div class="col-lg-12">
              <h3>Manage Networks <i class="glyphicon glyphicon-signal"></i></h3>
            </div>
          </div>
          <div class="row">
            <div class="col-lg-12"><a href="#" data-toggle="modal" data-target="#addNetworkModal">
                <p><i class="glyphicon glyphicon-plus"></i>Add network</p>
              </a></div>
          </div>
          <div class="row">
            <div class="col-lg-12">
              <table class="table table-condensed table-hover">
                <thead>
                  <tr>
                    <th>Scope</th>
                    <th>Description</th>
                    <th>Edit</th>
                    <th>Delete</th>
                  </tr>
                </thead>
                <tbody>
';

generate_nw_list();

echo '
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

    </div>
';

$frame->doc_end();

?>