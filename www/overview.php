<?php
session_start();

include_once('include/nwmanagement.php');
include_once('include/html_frame.php');

//Default range-view (TODO: delete init after implemented as user-default)
if ( isset( $_REQUEST[ 'nw_id' ] ) ) {
  $_SESSION[ 'cur_network_id' ] = $_REQUEST[ 'nw_id' ];
  $_SESSION[ 'current_page' ] = 1;
}

// Tag defaults to just 'show_all' if it's req'ed by client.
if ( isset( $_REQUEST[ 'show_all' ] ) ) {
  $filter_tags_length = count($_SESSION[ 'active_filter_tags' ] );
  if ( !$_SESSION[ 'show_all' ] ) {
    $_SESSION[ 'show_all' ] = true;
    $_SESSION[ 'active_filter_tags' ] = array();
  } else if( $filter_tags_length > 0 ) {
    $_SESSION[ 'show_all' ] = false;
  }
  $_SESSION[ 'current_page' ] = 1;
}

if ( isset( $_REQUEST[ 'filter_tag' ] ) ) {
  $req_tag = $_REQUEST[ 'filter_tag' ];
  $index = array_search($req_tag, $_SESSION[ 'active_filter_tags' ]);
  if ($index !== false) {
    unset($_SESSION[ 'active_filter_tags' ][$index]);
    $_SESSION[ 'active_filter_tags' ] = array_values($_SESSION[ 'active_filter_tags' ]);
  } else {
    $_SESSION[ 'active_filter_tags' ][] = $req_tag;
  }
  $filter_tags_length = count($_SESSION[ 'active_filter_tags' ] );
  if ($filter_tags_length === 0 || $filter_tags_length === 4) {
    $_SESSION[ 'active_filter_tags' ] = array();
    $_SESSION[ 'show_all' ] = true;
  } else {
    $_SESSION[ 'show_all' ] = false;
  }
  $_SESSION[ 'current_page' ] = 1;
}

if ( isset( $_REQUEST[ 'filter_search' ]) ) {
  $_SESSION[ 'filter_search' ] = $_REQUEST[ 'filter_search' ];
  $_SESSION[ 'current_page' ] = 1;
}

//TODO: result pages req's. Both from text and buttons. Validate input.
if ( isset( $_REQUEST[ 'result_page' ] )) {
  $page = $_REQUEST[ 'result_page' ];
  if ($page >= 1 && $page <= $_SESSION[ 'max_pages' ] ) {
    $_SESSION[ 'current_page' ] = $_REQUEST[ 'result_page' ];
  }
}

update_meta_data();

function calc_bit_mask() {
  $bitmask = 0;
  foreach ($_SESSION[ 'active_filter_tags' ] as $tag) {
      if ($tag == 'free') $bitmask |= 1;
      if ($tag == 'free_but_seen') $bitmask |= 2;
      if ($tag == 'taken') $bitmask |= 4;
      if ($tag == 'taken_not_seen') $bitmask |= 8;
  }
  return $bitmask;
}

//TODO: page per view from setting?
function update_meta_data() {
  $nwManager = new NetworkManagement();
  $result_set = $nwManager->getHosts($_SESSION[ 'cur_network_id' ], ($_SESSION[ 'current_page' ]-1), 100, $_SESSION[ 'filter_search' ], calc_bit_mask());

  $_SESSION[ 'networks' ] = $nwManager->getNetworks();
  $first_row = $result_set[0];
  $_SESSION[ 'result_set' ] = $result_set;
  $_SESSION[ 'host_rows' ] = $first_row['total_rows'];
  $_SESSION[ 'max_pages' ] = $first_row['total_pages'];
}

function network_ranges() {
  foreach ($_SESSION[ 'networks' ] as $range) {
    echo '      <li role="presentation"';
    if ($_SESSION[ 'cur_network_id' ] == $range['nw_id']) {
      echo ' class="active"';
    }
    echo '><a href="overview.php?nw_id='.$range["nw_id"].'">'.$range["nw_base"].'/'.$range["nw_cidr"].'</a></li>
      ';
  }
}

function network_description() {
  foreach ($_SESSION[ 'networks' ] as $range) {
    if ($_SESSION[ 'cur_network_id' ] == $range['nw_id']) {
      return $range['nw_description'];
    }
  }
}

//Controlling the toggling view of filters
function active_filter() {
  echo '
                <td><a class="filter-link" href="overview.php?show_all=true"><div class="toggle  '.compare_tags("show_all").'">Show all</div></a></td>
                <td><a class="filter-link" href="overview.php?filter_tag=free"><div class="toggle '.compare_tags("free").'"><div class="address-info free"></div>Free</div></a></td>
                <td><a class="filter-link" href="overview.php?filter_tag=free_but_seen"><div class="toggle '.compare_tags("free_but_seen").'"><div class="address-info free-but-seen"></div>Free (but seen)</div></a></td>
                <td><a class="filter-link" href="overview.php?filter_tag=taken"><div class="toggle '.compare_tags("taken").'"><div class="address-info taken"></div>Taken</div></a></td>
                <td><a class="filter-link" href="overview.php?filter_tag=taken_not_seen"><div class="toggle '.compare_tags("taken_not_seen").'"><div class="address-info taken-not-seen"></div>Taken (not seen)</div></a></td> 
  ';
}

//Helper function to active_filter()
function compare_tags($tag) {
  if ($tag === 'show_all' && $_SESSION['show_all']) {
    return 'active';
  }
  foreach ($_SESSION[ 'active_filter_tags' ] as $sesh_tag) {
    if ($sesh_tag === $tag) {
      return 'active';
    }
  }
}

function filter_search_placeholder() {
  if ($_SESSION[ 'filter_search' ] != null) {
    return $_SESSION[ 'filter_search' ];
  }
  return "Enter keywords";
}


//---------------------------------------------------
//---------------------------------------------------
//---------------------------------------------------
//---------------------------------------------------



function show_hosts() {
  $generated_table = "";
  foreach ($_SESSION[ 'result_set' ] as $host_row) {
    $generated_table .= show_host_row_view($host_row);
  }
  return $generated_table;
}

function show_host_row_view($row) {
  // ====================================
  // ALL POSSIBLE TAGS IN THE <INPUT> FOR CHECKBOX BELOW
  $ticked_box = '';

  //TODO: Fix if below?

  $nw_manager = new NetworkManagement();
  $cur_reservations = $nw_manager->getReserved( $_SESSION[ 'user_data' ][ 'usr_id' ] );
  if ( in_array( $row[ 'host_ip' ], $cur_reservations ) ) $ticked_box = ' checked';


  // Set the disabled tag if other user owns lock
  $disabled = '';
  if ( $row[ 'reserved_by_usern' ] !== 'nobody' && $row[ 'reserved_by_usern' ] !== $_SESSION[ 'user_data' ][ 'usr_usern' ] ) $disabled = ' disabled';
 
  $checkbox = '<input type="checkbox" id="cb'.$row['host_ip'].'" name="Kbook_ip" value="'.$row['host_ip'].'"'.$ticked_box.' '.$disabled.'>';
  if ( $disabled === ' disabled' ) $checkbox .= '<i class="glyphicon glyphicon-exclamation-sign"></i>';

  // ====================================
  // COLORING AND CHECKBOX SETTING BELOW
  $bootstrap_color_tag = '';

  //Free (but seen)
  if ( $row[ 'status' ] == 2 ) {
    $bootstrap_color_tag = ' danger';
  }

  //Taken
  if ( $row[ 'status' ] == 4 ) {
    $bootstrap_color_tag = ' warning';
    $checkbox = '';
  }

  // Taken (not seen)
  if ( $row[ 'status' ] == 8) {
    $bootstrap_color_tag = ' info';
    if (!$_SESSION[ 'steal_not_seen' ]) $checkbox = '';
  }
  // ====================================

  return '
                  <tr class="'.$bootstrap_color_tag.'">
                    <td data-toggle="collapse" data-target="#acc'.str_replace('.', '', $row['host_ip']).'" class="accordion-toggle" id="'.$row['host_ip'].'"><i class="glyphicon glyphicon-triangle-right"></i></td>
                    <td>'.$row['host_ip'].'</td>
                    <td>'.$row['host_name'].'</td>
                    <td colspan="2">'.substr($row['host_description'], 0, 30).' ...</td>
                    <td>'.substr($row['host_last_seen'], 0, 10).'</td>
                    <td title="Someone beat you to it...">'.$checkbox.'</td>
                  </tr>
                  <tr>
                    <td colspan="12" class="hiddenRow">
                      <div class="hiddenNwDiv accordion-body collapse" id="acc'.str_replace(".", "", $row['host_ip']).'">
                        <div class="row">
                          <div class="col-lg-6">
                            <h5>Data description</h5>
                            '.$row['host_description'].'
                          </div>
                          <div class="col-lg-3">
                            <h5>Last notified</h5>
                            '.$row['host_last_notified'].'
                            <div class="text-head-gutter"></div>
                            <h5>Lease expiry</h5>
                            '.$row['host_lease_expiry'].'  
                          </div>
                          <div class="col-lg-3">
                            <h5>User ID</h5>
                            <a href="mailto:'.$row['usr_email'].'"><i class="glyphicon glyphicon-envelope"></i>'.$row['usr_usern'].'</a>
                            <div class="text-head-gutter"></div>
                            <h5>Last scanned</h5>
                            '.$row['host_last_scanned'].'
                          </div>
                        </div>
                        <div class="row spacer-row"></div>
                      </div>
                    </td>
                  </tr>';
}


function basket() {
  $nw_manager = new NetworkManagement();
  $cur_reservations = $nw_manager->getReserved( $_SESSION[ 'user_data' ][ 'usr_id' ] );
  $content = '';
  foreach ($cur_reservations as $ip) {
    $content .= '<div class="clearfix" style="background-color: #f8f8f8; margin-bottom: 8px; padding:4px; display: block;">
                  <span class="small" style="float: left;">'.$ip.'</span>
                  <span style="float: right;" class="glyphicon glyphicon-minus"></span>
                </div>';
    //$content .= '<p style="width:100%;">'.$ip.'<span class="glyphicon glyphicon-minus al"></span></p>';
  }
  return $content;
}

//---------------------------------------------------
//---------------------------------------------------
//---------------------------------------------------
//---------------------------------------------------
//---------------------------------------------------



$frame = new HTMLframe();
//Starts generating html
$frame->doc_start("Hosts");
$frame->doc_nav("Overview", $_SESSION[ 'user_data' ][ 'usr_usern' ]);




//Range selection (with desc.) and info/filter panel below
echo '
    <div class="container">
      <div class="row">
        <div class="col-lg-offset-1 col-lg-8">
          <div class="row">
            <div class="col-lg-12">
              <ul class="nav nav-tabs"> 
';
network_ranges();
echo '
              </ul>
            </div>
          </div>
          <div class="row">
            <div class="col-lg-12">
              <h3>Description</h3>
            </div>
          </div>
          <div class="row">
            <div class="col-lg-12">
              <p>'.network_description().'</p>
            </div>
          </div>
          <!-- START - Filter and color-info row -->
          <table class="table filter small">
            <tbody>
              <tr>
';
active_filter();
echo '
                <td>&nbsp</td>
                <td>&nbsp</td>
              </tr>
              <tr>
                <td colspan="2" class="filter-bottom"><div class="filter-result"><em>'.$_SESSION[ 'host_rows' ].' address(es) in result</em></div></td>
                <td colspan="2" class="filter-bottom">
                  <form class="form" method="get">
                    <div class="input-group input-group-sm">
                      <input type="text" name="filter_search" class="form-control" placeholder="'.filter_search_placeholder().'">
                      <span class="input-group-btn">
                        <button class="btn btn-default" type="submit" value="Submit">Filter result</button>
                      </span>
                    </div>
                  </form>
                </td>
                <td class="filter-bottom" colspan="2">
                  <table class="table filter">
                    <tbody style="background-color:#eeeeee;">
                      <tr>
                        <td>&nbsp</td>
                        <td><div class="filter-bottom page">Page</div></td>
                        <td><form><input type="text" name="result_page" class="form-control input-sm result-page-field" placeholder="'.$_SESSION[ 'current_page' ].'" style="width:85%;margin-top:2px;"></form></td>
                        <td><div class="filter-bottom page">of '.$_SESSION[ 'max_pages' ].'</div></td>
                      </tr>
                    </tbody>
                  </table>
                </td>
                <td class="filter-bottom">
                  <form>
                    <div class="input-group input-group-sm">
                      <div class="input-group-btn" style="padding-right:5px;">
                        <button class="btn btn-default" type="submit" name="result_page" value="'.($_SESSION[ 'current_page' ]-1).'"><i class="glyphicon glyphicon-chevron-left"></i></button>
                        <button class="btn btn-default" type="submit" name="result_page" value="'.($_SESSION[ 'current_page' ]+1).'"><i class="glyphicon glyphicon-chevron-right"></i></button>
                      </div>
                    </div>
                  </form>
                </td>
              </tr>
            </tbody>
          </table>
          <!-- END - Filter and color-info row -->
';

// Host row (layout-element) start snippet
echo '
          <div class="row">
            <div class="col-lg-12">
';

//tbody_content($_SESSION['cur_network_id']);

echo '
              <table class="table table-condensed nw-table">
                <thead>
                  <tr>
                    <th></th>
                    <th>Host IP</th>
                    <th>Host name</th>
                    <th colspan="2">Data description</th>
                    <th>Last seen</th>
                    <th>Reserve</th>
                  </tr>
                </thead>
                <tbody>
                <form action="book_address.php" method="POST">

                  <!-- START - injection test -->
                  '.show_hosts().'
                  <!-- END - injection test -->

                  <input name="continue_reservation" value="SUBMIT" type="submit" id="submit-form" class="hidden" />

                </form>                  
                </tbody>
              </table>
';

// Host row (layout-element) end snippet
echo '
            </div>
          </div>
';

// Fixed right panel
echo '
        </div>

    <!-- FIXED RIGHT PANEL AND CHECKBOX FORM-BUTTON - START -->
        <div class="col-lg-3">
          <div class="affix fixed-right" id="choosenAddrDiv">
            <div class="panel panel-default">
              <div class="panel-heading">
                <p>Choosen addresses</p>
              </div>
              <div class="panel-body" id="choosenAddr">
                <p></p>
              </div>
              <div class="bookAddrBtn">
                <label for="submit-form" class="btn btn-primary">Book address(es)</label>
              </div>
            </div>
          </div>
        </div>
    <!-- FIXED RIGHT PANEL AND FORM-BUTTON - END -->

      </div>
    </div>
';

$frame->doc_end();
?>
