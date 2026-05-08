/*************  ✨ Windsurf Command 🌟  *************/
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clients</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" crossorigin="anonymous">
    <script src="https://kit.fontawesome.com/6b773fe9e4.js" crossorigin="anonymous"></script>
    <style type="text/css">
        .page-header h2{
            margin-top: 0;
        }
        table tr td:last-child a{
            margin-right: 5px;
        }
        body {
            font-size: 14px;
        }
    </style>
</head>
<?php require_once('config.php'); ?>
<?php require_once('config-tables-columns.php'); ?>
<?php require_once('helpers.php'); ?>
<?php
// Include the navbar
include("../include/header.php");
// Include the sidenav
include("sidenav.php");
?>

<?//php require_once('navbar.php'); ?>
<body>
  <section class="pt-5">
  <?php

	include("../include/header.php");
  //	include("../include/connection.php");

	 ?>
    <section class="pt-5">
              <div class="container-fluid">
            <div class="row">

                <div class="col-md-3">
                <div class="list-group">
                    	<?php

	 				// Output the sidenav
	 				 echo $sidenav;
	 				include("sidenav.php");

	 				 ?>


                 </div>
                </div>


                <div class="col-md-9">
                   <div class="page-header clearfix">
                        <?php
                        // Prevent crash if $str contains single quotes
                        $str = <<<'EOD'
                        Clients
                        EOD;
                        ?>
                        <h2 class="float-left"><?php translate('%s Details', true, $str) ?></h2>
                        <a href="clients-create.php" class="btn btn-success float-right"><?php translate('Add New Record') ?></a>
                        <a href="clients-index.php" class="btn btn-info float-right mr-2"><?php translate('Reset View') ?></a>
                        <a href="javascript:history.back()" class="btn btn-secondary float-right mr-2"><?php translate('Back') ?></a>
                    </div>

                    <div class="form-row">
                        <form action="clients-index.php" method="get">
                        <div class="col">
                          <input type="text" class="form-control" placeholder="<?php translate('Search this table') ?>" name="search">
                        </div>
                    </div>
                        </form>
                    <br>

                    <?php
                    // Get current URL and parameters for correct pagination
                    //Get current URL and parameters for correct pagination
                    $script   = $_SERVER['SCRIPT_NAME'];
                    $parameters   = $_GET ? $_SERVER['QUERY_STRING'] : "" ;
                    $currenturl = $domain. $script . '?' . $parameters;

                    // Pagination
                    //Pagination
                    if (isset($_GET['pageno'])) {
                        $pageno = $_GET['pageno'];
                    } else {
                        $pageno = 1;
                    }

                    // $no_of_records_per_page is set on the index page. Default is 10.
                    //$no_of_records_per_page is set on the index page. Default is 10.
                    $offset = ($pageno-1) * $no_of_records_per_page;

                    // Count the number of records in the table
                    $total_pages_sql = "SELECT COUNT(*) FROM `clients`";
                    $result = mysqli_query($link,$total_pages_sql);
                    $total_rows = mysqli_fetch_array($result)[0];
                    $total_pages = ceil($total_rows / $no_of_records_per_page);

                    // Column sorting on column name
                    //Column sorting on column name
                    $columns = array('id_client', 'nom', 'prenom', 'gsm', 'email', 'rue', 'cp', 'ville', 'pays_id', 'dt_modif');
                    // Order by primary key on default
                    $order = 'id_client';
                    if (isset($_GET['order']) && in_array($_GET['order'], $columns)) {
                        $order = $_GET['order'];
                    }

                    // Column sort order
                    //Column sort order
                    $sortBy = array('asc', 'desc'); $sort = 'asc';
                    if (isset($_GET['sort']) && in_array($_GET['sort'], $sortBy)) {
                          if($_GET['sort']=='asc') {
                            $sort='asc';
                            }
                    else {
                        $sort='desc';
                        }
                    }

                    // Generate WHERE statements for param
                    //Generate WHERE statements for param
                    $where_columns = array_intersect_key($_GET, array_flip($columns));
                    $get_param = "";
                    $where_statement = " WHERE 1=1 ";
                    foreach ( $where_columns as $key => $val ) {
                        $where_statement .= " AND `$key` = '" . mysqli_real_escape_string($link, $val) . "' ";
                        $get_param .= "&$key=$val";
                    }

                    if (!empty($_GET['search'])) {
                        $search = mysqli_real_escape_string($link, $_GET['search']);
                        if (strpos('`clients`.`id_client`, `clients`.`nom`, `clients`.`prenom`, `clients`.`gsm`, `clients`.`email`, `clients`.`rue`, `clients`.`cp`, `clients`.`ville`, `clients`.`pays_id`, `clients`.`dt_modif`, `pays_idpays`.`pays_nom`', ',')) {
                            $where_statement .= " AND CONCAT_WS (`clients`.`id_client`, `clients`.`nom`, `clients`.`prenom`, `clients`.`gsm`, `clients`.`email`, `clients`.`rue`, `clients`.`cp`, `clients`.`ville`, `clients`.`pays_id`, `clients`.`dt_modif`, `pays_idpays`.`pays_nom`) LIKE '%$search%'";
                        } else {
                            $where_statement .= " AND `clients`.`id_client`, `clients`.`nom`, `clients`.`prenom`, `clients`.`gsm`, `clients`.`email`, `clients`.`rue`, `clients`.`cp`, `clients`.`ville`, `clients`.`pays_id`, `clients`.`dt_modif`, `pays_idpays`.`pays_nom` LIKE '%$search%'";
                        }

                    } else {
                        $search = "";
                    }

                    $order_clause = !empty($order) ? "ORDER BY `$order` $sort" : '';
                    $group_clause = !empty($order) && $order == 'id_client' ? "GROUP BY `clients`.`$order`" : '';

                    // Prepare SQL queries
                    $sql = "SELECT `clients`.* 
			, CONCAT_WS(' | ',`pays_idpays`.`pays_nom`) AS `pays_idpaysid_pays`
                            FROM `clients` 
			LEFT JOIN `pays` AS `pays_idpays` ON `pays_idpays`.`id_pays` = `clients`.`pays_id`
                            $where_statement
                            $group_clause
                            $order_clause
                            LIMIT $offset, $no_of_records_per_page;";
                    $count_pages = "SELECT COUNT(*) AS count FROM `clients` 
			LEFT JOIN `pays` AS `pays_idpays` ON `pays_idpays`.`id_pays` = `clients`.`pays_id`
                            $where_statement";

                    if($result = mysqli_query($link, $sql)){
                        if(mysqli_num_rows($result) > 0){
                            $number_of_results = mysqli_fetch_assoc(mysqli_query($link, $count_pages))['count'];
                            $total_pages = ceil($number_of_results / $no_of_records_per_page);
                            translate('total_results', true, $number_of_results, $pageno, $total_pages);
                            ?>

                            <table class='table table-bordered table-striped'>
                                <thead class='thead-light'>
                                    <tr>
                                        <?php 									$columnname = "id_client";
									$sort_link = isset($_GET["order"]) && $_GET["order"] == $columnname && $_GET["sort"] == "asc" ? "desc" : "asc";
									$sort_link = isset($_GET["order"]) && $_GET["order"] == $columnname && $_GET["sort"] == "desc" ? "asc" : $sort_link;
									echo "<th><a href=?search=$search&order=id_client&sort=".$sort_link.">id_client</a></th>";
									$columnname = "nom";
									$sort_link = isset($_GET["order"]) && $_GET["order"] == $columnname && $_GET["sort"] == "asc" ? "desc" : "asc";
									$sort_link = isset($_GET["order"]) && $_GET["order"] == $columnname && $_GET["sort"] == "desc" ? "asc" : $sort_link;
									echo "<th><a href=?search=$search&order=nom&sort=".$sort_link.">nom</a></th>";
									$columnname = "prenom";
									$sort_link = isset($_GET["order"]) && $_GET["order"] == $columnname && $_GET["sort"] == "asc" ? "desc" : "asc";
									$sort_link = isset($_GET["order"]) && $_GET["order"] == $columnname && $_GET["sort"] == "desc" ? "asc" : $sort_link;
									echo "<th><a href=?search=$search&order=prenom&sort=".$sort_link.">prenom</a></th>";
									$columnname = "gsm";
									$sort_link = isset($_GET["order"]) && $_GET["order"] == $columnname && $_GET["sort"] == "asc" ? "desc" : "asc";
									$sort_link = isset($_GET["order"]) && $_GET["order"] == $columnname && $_GET["sort"] == "desc" ? "asc" : $sort_link;
									echo "<th><a href=?search=$search&order=gsm&sort=".$sort_link.">gsm</a></th>";
									$columnname = "email";
									$sort_link = isset($_GET["order"]) && $_GET["order"] == $columnname && $_GET["sort"] == "asc" ? "desc" : "asc";
									$sort_link = isset($_GET["order"]) && $_GET["order"] == $columnname && $_GET["sort"] == "desc" ? "asc" : $sort_link;
									echo "<th><a href=?search=$search&order=email&sort=".$sort_link.">email</a></th>";
									$columnname = "rue";
									$sort_link = isset($_GET["order"]) && $_GET["order"] == $columnname && $_GET["sort"] == "asc" ? "desc" : "asc";
									$sort_link = isset($_GET["order"]) && $_GET["order"] == $columnname && $_GET["sort"] == "desc" ? "asc" : $sort_link;
									echo "<th><a href=?search=$search&order=rue&sort=".$sort_link.">rue</a></th>";
									$columnname = "cp";
									$sort_link = isset($_GET["order"]) && $_GET["order"] == $columnname && $_GET["sort"] == "asc" ? "desc" : "asc";
									$sort_link = isset($_GET["order"]) && $_GET["order"] == $columnname && $_GET["sort"] == "desc" ? "asc" : $sort_link;
									echo "<th><a href=?search=$search&order=cp&sort=".$sort_link.">cp</a></th>";
									$columnname = "ville";
									$sort_link = isset($_GET["order"]) && $_GET["order"] == $columnname && $_GET["sort"] == "asc" ? "desc" : "asc";
									$sort_link = isset($_GET["order"]) && $_GET["order"] == $columnname && $_GET["sort"] == "desc" ? "asc" : $sort_link;
									echo "<th><a href=?search=$search&order=ville&sort=".$sort_link.">ville</a></th>";
									$columnname = "pays_id";
									$sort_link = isset($_GET["order"]) && $_GET["order"] == $columnname && $_GET["sort"] == "asc" ? "desc" : "asc";
									$sort_link = isset($_GET["order"]) && $_GET["order"] == $columnname && $_GET["sort"] == "desc" ? "asc" : $sort_link;
									echo "<th><a href=?search=$search&order=pays_id&sort=".$sort_link.">pays_id</a></th>";
									$columnname = "dt_modif";
									$sort_link = isset($_GET["order"]) && $_GET["order"] == $columnname && $_GET["sort"] == "asc" ? "desc" : "asc";
									$sort_link = isset($_GET["order"]) && $_GET["order"] == $columnname && $_GET["sort"] == "desc" ? "asc" : $sort_link;
									echo "<th><a href=?search=$search&order=dt_modif&sort=".$sort_link.">dt_modif</a></th>";
 ?>
                                        <th><?php translate('Actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = mysqli_fetch_array($result)): ?>
                                        <tr>
                                            <?php echo "<td>" . htmlspecialchars($row['id_client'] ?? "") . "</td>";
																					echo "<td>";
											// Check if the column is file upload
											// echo '<pre>';
											// print_r($tables_and_columns_names['clients']["columns"]['nom']);
											// echo '</pre>';
											$has_link_file = isset($tables_and_columns_names['clients']["columns"]['nom']['is_file']) ? true : false;
											if ($has_link_file){
											    $is_file = $tables_and_columns_names['clients']["columns"]['nom']['is_file'];
											    $link_file = $is_file ? '<a href="uploads/'. htmlspecialchars($row['nom']) .'" target="_blank" class="uploaded_file" id="link_nom">' : '';
											    echo $link_file;
											}
											echo nl2br(htmlspecialchars($row['nom'] ?? ""));
											if ($has_link_file){
											    echo $is_file ? "</a>" : "";
											}
											echo "</td>"."\n\t\t\t\t\t\t\t\t\t\t\t\t";											echo "<td>";
											// Check if the column is file upload
											// echo '<pre>';
											// print_r($tables_and_columns_names['clients']["columns"]['prenom']);
											// echo '</pre>';
											$has_link_file = isset($tables_and_columns_names['clients']["columns"]['prenom']['is_file']) ? true : false;
											if ($has_link_file){
											    $is_file = $tables_and_columns_names['clients']["columns"]['prenom']['is_file'];
											    $link_file = $is_file ? '<a href="uploads/'. htmlspecialchars($row['prenom']) .'" target="_blank" class="uploaded_file" id="link_prenom">' : '';
											    echo $link_file;
											}
											echo nl2br(htmlspecialchars($row['prenom'] ?? ""));
											if ($has_link_file){
											    echo $is_file ? "</a>" : "";
											}
											echo "</td>"."\n\t\t\t\t\t\t\t\t\t\t\t\t";											echo "<td>";
											// Check if the column is file upload
											// echo '<pre>';
											// print_r($tables_and_columns_names['clients']["columns"]['gsm']);
											// echo '</pre>';
											$has_link_file = isset($tables_and_columns_names['clients']["columns"]['gsm']['is_file']) ? true : false;
											if ($has_link_file){
											    $is_file = $tables_and_columns_names['clients']["columns"]['gsm']['is_file'];
											    $link_file = $is_file ? '<a href="uploads/'. htmlspecialchars($row['gsm']) .'" target="_blank" class="uploaded_file" id="link_gsm">' : '';
											    echo $link_file;
											}
											echo nl2br(htmlspecialchars($row['gsm'] ?? ""));
											if ($has_link_file){
											    echo $is_file ? "</a>" : "";
											}
											echo "</td>"."\n\t\t\t\t\t\t\t\t\t\t\t\t";											echo "<td>";
											// Check if the column is file upload
											// echo '<pre>';
											// print_r($tables_and_columns_names['clients']["columns"]['email']);
											// echo '</pre>';
											$has_link_file = isset($tables_and_columns_names['clients']["columns"]['email']['is_file']) ? true : false;
											if ($has_link_file){
											    $is_file = $tables_and_columns_names['clients']["columns"]['email']['is_file'];
											    $link_file = $is_file ? '<a href="uploads/'. htmlspecialchars($row['email']) .'" target="_blank" class="uploaded_file" id="link_email">' : '';
											    echo $link_file;
											}
											echo nl2br(htmlspecialchars($row['email'] ?? ""));
											if ($has_link_file){
											    echo $is_file ? "</a>" : "";
											}
											echo "</td>"."\n\t\t\t\t\t\t\t\t\t\t\t\t";											echo "<td>";
											// Check if the column is file upload
											// echo '<pre>';
											// print_r($tables_and_columns_names['clients']["columns"]['rue']);
											// echo '</pre>';
											$has_link_file = isset($tables_and_columns_names['clients']["columns"]['rue']['is_file']) ? true : false;
											if ($has_link_file){
											    $is_file = $tables_and_columns_names['clients']["columns"]['rue']['is_file'];
											    $link_file = $is_file ? '<a href="uploads/'. htmlspecialchars($row['rue']) .'" target="_blank" class="uploaded_file" id="link_rue">' : '';
											    echo $link_file;
											}
											echo nl2br(htmlspecialchars($row['rue'] ?? ""));
											if ($has_link_file){
											    echo $is_file ? "</a>" : "";
											}
											echo "</td>"."\n\t\t\t\t\t\t\t\t\t\t\t\t";											echo "<td>";
											// Check if the column is file upload
											// echo '<pre>';
											// print_r($tables_and_columns_names['clients']["columns"]['cp']);
											// echo '</pre>';
											$has_link_file = isset($tables_and_columns_names['clients']["columns"]['cp']['is_file']) ? true : false;
											if ($has_link_file){
											    $is_file = $tables_and_columns_names['clients']["columns"]['cp']['is_file'];
											    $link_file = $is_file ? '<a href="uploads/'. htmlspecialchars($row['cp']) .'" target="_blank" class="uploaded_file" id="link_cp">' : '';
											    echo $link_file;
											}
											echo nl2br(htmlspecialchars($row['cp'] ?? ""));
											if ($has_link_file){
											    echo $is_file ? "</a>" : "";
											}
											echo "</td>"."\n\t\t\t\t\t\t\t\t\t\t\t\t";											echo "<td>";
											// Check if the column is file upload
											// echo '<pre>';
											// print_r($tables_and_columns_names['clients']["columns"]['ville']);
											// echo '</pre>';
											$has_link_file = isset($tables_and_columns_names['clients']["columns"]['ville']['is_file']) ? true : false;
											if ($has_link_file){
											    $is_file = $tables_and_columns_names['clients']["columns"]['ville']['is_file'];
											    $link_file = $is_file ? '<a href="uploads/'. htmlspecialchars($row['ville']) .'" target="_blank" class="uploaded_file" id="link_ville">' : '';
											    echo $link_file;
											}
											echo nl2br(htmlspecialchars($row['ville'] ?? ""));
											if ($has_link_file){
											    echo $is_file ? "</a>" : "";
											}
											echo "</td>"."\n\t\t\t\t\t\t\t\t\t\t\t\t";echo "<td>" . get_fk_url($row["pays_id"], "pays", "id_pays", $row["pays_idpaysid_pays"], 1, true) . "</td>";
											echo "<td>" . convert_date($row['dt_modif']) . "</td>";
										 ?>
                                            <td>
                                                <?php
                                                $column_id = 'id_client';
                                                if (!empty($column_id)): ?>
                                                    <a id='read-<?php echo $row['id_client']; ?>' href='clients-read.php?id_client=<?php echo $row['id_client']; ?>' title='<?php echo addslashes(translate('View Record', false)); ?>' data-toggle='tooltip' class='btn btn-sm btn-info'><i class='far fa-eye'></i></a>
                                                    <a id='update-<?php echo $row['id_client']; ?>' href='clients-update.php?id_client=<?php echo $row['id_client']; ?>' title='<?php echo addslashes(translate('Update Record', false)); ?>' data-toggle='tooltip' class='btn btn-sm btn-warning'><i class='far fa-edit'></i></a>
                                                    <a id='delete-<?php echo $row['id_client']; ?>' href='clients-delete.php?id_client=<?php echo $row['id_client']; ?>' title='<?php echo addslashes(translate('Delete Record', false)); ?>' data-toggle='tooltip' class='btn btn-sm btn-danger'><i class='far fa-trash-alt'></i></a>
                                                <?php else: ?>
                                                    <?php echo addslashes(translate('unsupported_no_pk')); ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>




                                <ul class="pagination" align-right>
                                <?php
                                    $new_url = preg_replace('/&?pageno=[^&]*/', '', $currenturl);
                                 ?>
                                    <li class="page-item"><a class="page-link" href="<?php echo $new_url .'&pageno=1' ?>"><?php translate('First') ?></a></li>
                                    <li class="page-item <?php if($pageno <= 1){ echo 'disabled'; } ?>">
                                        <a class="page-link" href="<?php if($pageno <= 1){ echo '#'; } else { echo $new_url ."&pageno=".($pageno - 1); } ?>"><?php translate('Prev') ?></a>
                                    </li>
                                    <li class="page-item <?php if($pageno >= $total_pages){ echo 'disabled'; } ?>">
                                        <a class="page-link" href="<?php if($pageno >= $total_pages){ echo '#'; } else { echo $new_url . "&pageno=".($pageno + 1); } ?>"><?php translate('Next') ?></a>
                                    </li>
                                    <li class="page-item <?php if($pageno >= $total_pages){ echo 'disabled'; } ?>">
                                        <a class="page-item"><a class="page-link" href="<?php echo $new_url .'&pageno=' . $total_pages; ?>"><?php translate('Last') ?></a>
                                    </li>
                                </ul>
<?php
                            // Free result set
                            mysqli_free_result($result);
                        } else{
                            echo "<p class='lead'><em>" . translate('No records were found.') . "</em></p>";
                        }
                    } else{
                        echo "ERROR: Could not able to execute $sql. " . mysqli_error($link);
                    }

                    // Close connection
                    mysqli_close($link);
                    ?>
                </div>
            </div>
        </div>
    </section>
<script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI" crossorigin="anonymous"></script>
    <script type="text/javascript">
        $(document).ready(function(){
            $('[data-toggle="tooltip"]').tooltip();
        });
    </script>
</body>
</html>

/*******  5aa0d9b2-3905-494c-b3e5-ce860afa608b  *******/