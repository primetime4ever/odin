<?php

session_start();

include_once('include/html_frame.php');
include_once('include/nwmanagement.php');

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
          <div class="modal-body">
            <form>
              <div class="form-group">
                <label for="network">Network</label>
                <input type="text" class="form-control" id="network" placeholder="Network">
              </div>
              <div class="form-group">
                <label for="CIDR">CIDR/Netmask</label>
                <input type="text" class="form-control" id="CIDR" placeholder="Write subnet in CIDR or Netmask notation">
              </div>
              <div class="form-group">
                <label for="networkDescription">Network description</label>
                <textarea class="form-control" rows="3" id="networkDescription" placeholder="Network description"></textarea>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            <button type="button" class="btn btn-primary">Save changes</button>
          </div>
        </div>
      </div>
    </div>
<!-- Modal ADD NETWORK code end -->

<!-- Modal DELETE NETWORK code start -->
    <div class="modal fade" id="deleteNetworkModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title" id="myModalLabel">Delete network #range</h4>
          </div>
          <div class="modal-body">
            <form>
              <div class="form-group">
                <p>It is strongly advised to notify current and recent users in conjunction with deleting the network range. Please provide a message below providing information about new available ranges and somewhere to direct questions. Also remember that deleting the range does not actually free the physical hold of addresses users still might have. Have a nice day and so on.</p>
              </div>
              <div class="form-group">
                <label for="notificationMessage">Notification message</label>
                <textarea class="form-control" rows="3" id="notificationMessage" placeholder="Notification message"></textarea>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            <button type="button" class="btn btn-primary">Notify users</button>
            <button type="button" class="btn btn-primary">Delete range and notify users</button>
          </div>
        </div>
      </div>
    </div>
<!-- Modal DELETE NETWORK code end -->
';

$frame->doc_nav("Networks", $_SESSION[ 'username' ]);

echo '
    <div class="container">
      <div class="row">
        <div class="col-lg-offset-1 col-lg-8">

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
              <table class="table table-condensed">
                <thead>
                  <tr>
                    <th>Network ID</th>
                    <th>Scope</th>
                    <th>Delete</th>
                  </tr>
                </thead>
                <tbody>

                 <!-- tr*3>td{$}+td{192.168.0.0/24}+(td>a[data-toggle="modal"][data-target="#deleteNetworkModal"]>i.glyphicon.glyphicon-trash) -->

                 <tr>
                    <td>1</td>
                    <td>192.168.0.0/24</td>
                    <td><a href="#" data-toggle="modal" data-target="#deleteNetworkModal"><i class="glyphicon glyphicon-trash"></i></a></td>
                  </tr>
                  <tr>
                    <td>2</td>
                    <td>192.172.0.0/24</td>
                    <td><a href="#" data-toggle="modal" data-target="#deleteNetworkModal"><i class="glyphicon glyphicon-trash"></i></a></td>
                  </tr>
                  <tr>
                    <td>3</td>
                    <td>192.176.0.0/24</td>
                    <td><a href="#" data-toggle="modal" data-target="#deleteNetworkModal"><i class="glyphicon glyphicon-trash"></i></a></td>
                  </tr> 
                  
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