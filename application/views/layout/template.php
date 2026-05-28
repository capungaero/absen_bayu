<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="utf-8" />
        <title>SAG</title>
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <meta content="Premium Multipurpose Admin & Dashboard Template" name="description" />
        <meta content="" name="author" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />

        <!-- App favicon -->
        <link rel="shortcut icon" href="<?= base_url() ?>assets/images/logo-box.png">

        <!-- App css -->
        <link href="<?= base_url() ?>assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
        <link href="<?= base_url() ?>assets/css/icons.min.css" rel="stylesheet" type="text/css" />
        <link href="<?= base_url() ?>assets/css/metisMenu.min.css" rel="stylesheet" type="text/css" />
        <link href="<?= base_url() ?>assets/plugins/daterangepicker/daterangepicker.css" rel="stylesheet" type="text/css" />
        <link href="<?= base_url() ?>assets/css/app.min.css" rel="stylesheet" type="text/css" />

        <link href="<?= base_url() ?>assets/plugins/datatables/dataTables.bootstrap4.min.css" rel="stylesheet" type="text/css" />
        <link href="<?= base_url() ?>assets/plugins/datatables/buttons.bootstrap4.min.css" rel="stylesheet" type="text/css" />
        <!-- Responsive datatable examples -->
        <link href="<?= base_url() ?>assets/plugins/datatables/responsive.bootstrap4.min.css" rel="stylesheet" type="text/css" /> 
        <link href="<?= base_url() ?>assets/plugins/sweet-alert2/sweetalert2.min.css" rel="stylesheet" type="text/css">

        <link href="<?= base_url() ?>assets/plugins/timepicker/bootstrap-material-datetimepicker.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
        <link href="<?= base_url() ?>assets/plugins/summernote/summernote-bs4.min.css" rel="stylesheet" />

        <script src="<?= base_url() ?>assets/js/jquery.min.js"></script>

        <script src="<?= base_url() ?>assets/js/sweetalert.min.js"></script>

        <script src="<?= base_url() ?>assets/plugins/datatables/jquery.dataTables.min.js"></script>
        <script src="<?= base_url() ?>assets/plugins/datatables/dataTables.bootstrap4.min.js"></script>
        <!-- Buttons examples -->
        <script src="<?= base_url() ?>assets/plugins/datatables/dataTables.buttons.min.js"></script>
        <script src="<?= base_url() ?>assets/plugins/datatables/buttons.bootstrap4.min.js"></script>
        <script src="<?= base_url() ?>assets/plugins/datatables/jszip.min.js"></script>
        <script src="<?= base_url() ?>assets/plugins/datatables/pdfmake.min.js"></script>
        <script src="<?= base_url() ?>assets/plugins/datatables/vfs_fonts.js"></script>
        <script src="<?= base_url() ?>assets/plugins/datatables/buttons.html5.min.js"></script>
        <script src="<?= base_url() ?>assets/plugins/datatables/buttons.print.min.js"></script>
        <script src="<?= base_url() ?>assets/plugins/datatables/buttons.colVis.min.js"></script>
        <!-- Responsive examples -->
        <script src="<?= base_url() ?>assets/plugins/datatables/dataTables.responsive.min.js"></script>
        <script src="<?= base_url() ?>assets/plugins/datatables/responsive.bootstrap4.min.js"></script>
        <script src="<?= base_url() ?>assets/plugins/repeater/jquery.repeater.min.js"></script>
        <script src="<?= base_url() ?>assets/pages/jquery.form-repeater.js"></script>

        <style type="text/css">
            @media only screen and (min-width: 902px) {
              .setHeader{
                margin-left: 3rem !important;
              }
            }
        </style>

    </head>

    <body data-layout="horizontal" class="" style="background-color: #fdfafa">
        
       <!-- Top Bar Start -->
       <div class="topbar" style="box-shadow: 0 4px 8px -5px rgba(0,0,0,0.1); position: fixed;">  
         
                <!-- LOGO -->
                <div class="brand setHeader">
                    <a href="index.html" class="logo">
                        <span>
                            <img src="<?= base_url() ?>assets/images/logo-horizontal.png" alt="logo-small" style="width: 150px">
                        </span>
                        <span>
                            <img src="<?= base_url() ?>assets/images/logo-horizontal.png" alt="logo-large" class="logo-lg logo-light" style="width: 100px">
                        </span>
                    </a>
                </div>
                <!--end logo-->  
                <!-- Navbar -->
                <nav class="navbar-custom setHeader">    
                    <ul class="list-unstyled topbar-nav float-right mb-0"> 
                        <?php 
                        
                        if(!$this->ion_auth->logged_in()){ ?>

                            <li class="menu-item">
                                <div class="nav-link">
                                    <a class=" btn btn btn-primary" href="<?= site_url('authentication/login') ?>" role="button"></i>Login Disini</a>
                                </div>                                
                            </li> 

                        <?php }else{ 
                            $user = $this->ion_auth->user()->row();
                            $role = $this->ion_auth->get_users_groups()->row()->name;
                        ?>       
                            <li class="dropdown">
                                <a class="nav-link dropdown-toggle waves-effect waves-light nav-user" data-toggle="dropdown" href="#" role="button"
                                    aria-haspopup="false" aria-expanded="false">
                                    <span class="ml-1 nav-user-name hidden-sm"><?= $user->first_name ?></span>
                                    <img src="<?= base_url('assets/images/users/'.$user->photo) ?>" alt="profile-user" class="rounded-circle thumb-sm" />                                 
                                </a>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <a class="dropdown-item" href="<?= site_url('user/profile') ?>"><i data-feather="user" class="align-self-center icon-xs icon-dual mr-1"></i> Profile</a>

                                    <a class="dropdown-item" href="<?= site_url('user/project') ?>"><i data-feather="file-text" class="align-self-center icon-xs icon-dual mr-1"></i> Riset Saya</a>

                                    <?php if($role == 'standard-user'){ ?>
                                        <a class="dropdown-item" href="<?= site_url('user/information') ?>"><i data-feather="layers" class="align-self-center icon-xs icon-dual mr-1"></i> Data Diri</a>
                                    <?php } ?>
                                    
                                    <div class="dropdown-divider mb-0"></div>
                                    <a class="dropdown-item" href="<?= site_url('logout') ?>"><i data-feather="power" class="align-self-center icon-xs icon-dual mr-1"></i> Logout</a>
                                </div>
                            </li>

                        <?php } ?>
                         
                        
                        <li class="menu-item">
                            <!-- Mobile menu toggle-->
                            <a class="navbar-toggle nav-link" id="mobileToggle">
                                <div class="lines">
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>
                            </a><!-- End mobile menu toggle-->
                        </li> <!--end menu item-->   
                    </ul><!--end topbar-nav-->

                    <div class="navbar-custom-menu">
                        <div id="navigation">
                            <!-- Navigation Menu-->
                            <ul class="navigation-menu">
                                <li>
                                    <a href="<?= site_url() ?>">
                                        <span><i data-feather="home" class="align-self-center hori-menu-icon"></i>Home</span>
                                    </a>
                                </li>

                                <li>
                                    <a href="<?= site_url('project') ?>">
                                        <span><i data-feather="search" class="align-self-center hori-menu-icon"></i>Cari Riset Sekarang</span>
                                    </a>
                                </li>
        
                                <li>
                                    <a href="<?= site_url('page/about_us') ?>">
                                        <span><i data-feather="star" class="align-self-center hori-menu-icon"></i>Tentang Kami</span>
                                    </a>
                                </li>
                            </ul><!-- End navigation menu -->
                        </div> <!-- end navigation -->
                    </div>
                    <!-- Navbar -->
                </nav>
                <!-- end navbar-->
        </div>
        <!-- Top Bar End -->
        <div class="page-wrapper">
            <!-- Page Content-->

            <?= $contents ?>
            
            <!-- end page content -->
        </div>
        <!-- end page-wrapper -->
        <footer class="footer text-center text-sm-left bg-white">
            <div class="boxed-footer">© 2020 SAG by Wader Jhonson
            </div>
        </footer>

        


        <!-- jQuery  -->
        
        <script src="<?= base_url() ?>assets/js/bootstrap.bundle.min.js"></script>
        <script src="<?= base_url() ?>assets/js/metismenu.min.js"></script>
        <script src="<?= base_url() ?>assets/js/waves.js"></script>
        <script src="<?= base_url() ?>assets/js/feather.min.js"></script>
        <script src="<?= base_url() ?>assets/js/simplebar.min.js"></script>
        <script src="<?= base_url() ?>assets/js/moment.js"></script>
        <script src="<?= base_url() ?>assets/plugins/daterangepicker/daterangepicker.js"></script>

        <script src="<?= base_url() ?>assets/plugins/apex-charts/apexcharts.min.js"></script>
        <script src="<?= base_url() ?>assets/plugins/timepicker/bootstrap-material-datetimepicker.js"></script>
        <script src="<?= base_url() ?>assets/plugins/summernote/summernote-bs4.min.js"></script>

        <!-- App js -->
        <script src="<?= base_url() ?>assets/js/app.js"></script>
        
        <script type="text/javascript">
            function show_loading(){
                return '<div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>';
            }

            $('.summernote').summernote({
                height: 150,                 // set editor height
                minHeight: null,             // set minimum height of editor
                maxHeight: null,             // set maximum height of editor
                focus: false                 // set focus to editable area after initializing summernote
            });

            $(".mdate").bootstrapMaterialDatePicker({
                weekStart: 0,
                minDate : "<?= date('Y-m-d') ?>",
                time : false,
                lang : 'tr'
            });

            $('.datatable').DataTable();
        </script>
    </body>

</html>