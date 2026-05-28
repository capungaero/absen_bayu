<!doctype html>
<html lang="en">

<head>
        
        <meta charset="utf-8" />
        <title>E-ABSENSI TIFFANY HOUSEWARE & MART</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta content="Premium Multipurpose Admin & Dashboard Template" name="description" />
        <meta content="Themesbrand" name="author" />
        <!-- App favicon -->
        <link rel="shortcut icon" href="<?= base_url() ?>assets/images/favicon.ico">

        <!-- Bootstrap Css -->
        <link href="<?= base_url() ?>assets/css/bootstrap.min.css" id="bootstrap-style" rel="stylesheet" type="text/css" />
        <!-- Icons Css -->
        <link href="<?= base_url() ?>assets/css/icons.min.css" rel="stylesheet" type="text/css" />
        <!-- App Css-->
        <link href="<?= base_url() ?>assets/css/app.min.css" id="app-style" rel="stylesheet" type="text/css" />
        <link href="<?= base_url() ?>assets/libs/select2/css/select2.min.css" rel="stylesheet" type="text/css" />

        <link href="<?= base_url() ?>assets/libs/timepicker/bootstrap-material-datetimepicker.css" rel="stylesheet">

        <link href="<?= base_url() ?>assets/libs/datatables.net-bs4/css/dataTables.bootstrap4.min.css" rel="stylesheet" type="text/css" />
        <link href="<?= base_url() ?>assets/libs/datatables.net-buttons-bs4/css/buttons.bootstrap4.min.css" rel="stylesheet" type="text/css" />
        <link href="<?= base_url() ?>assets/libs/datatables.net-responsive-bs4/css/responsive.bootstrap4.min.css" rel="stylesheet" type="text/css" />    
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
        <link href="<?= base_url() ?>assets/libs/sweetalert2/sweetalert2.min.css" rel="stylesheet" type="text/css" />

        <script src="<?= base_url() ?>assets/libs/jquery/jquery.min.js"></script>
        <script src="<?= base_url() ?>assets/js/format_rp.js"></script>

        <script src="<?= base_url() ?>assets/libs/datatables.net/js/jquery.dataTables.min.js"></script>
        <script src="<?= base_url() ?>assets/libs/datatables.net-bs4/js/dataTables.bootstrap4.min.js"></script>
        <script src="<?= base_url() ?>assets/libs/datatables.net-responsive/js/dataTables.responsive.min.js"></script>
        <script src="<?= base_url() ?>assets/libs/datatables.net-responsive-bs4/js/responsive.bootstrap4.min.js"></script>

        <script src="<?= base_url() ?>assets/libs/select2/js/select2.min.js"></script>

        <script src="https://unpkg.com/gijgo@1.9.13/js/gijgo.min.js" type="text/javascript"></script>
        <link href="https://unpkg.com/gijgo@1.9.13/css/gijgo.min.css" rel="stylesheet" type="text/css" />
        
        <style type="text/css">
            .dtp .p10 > a { color: #ffffff; text-decoration: none; }

            .select2-selection__rendered {
                line-height: 31px !important;
            }
            .select2-container .select2-selection--single {
                height: 35px !important;
            }
            .select2-selection__arrow {
                height: 34px !important;
            }

            .select2-drop li {
              white-space: pre-line;
            }
        </style>
    </head>

    <body data-layout="horizontal" data-topbar="colored">

        <!-- Begin page -->
        <div id="layout-wrapper">

            <header id="page-topbar">
                <div class="navbar-header">
                    <div class="d-flex">
                        <!-- LOGO -->
                        <div class="navbar-brand-box">
                            <a href="<?= site_url('dashboard') ?>" class="logo logo-dark">
                                <span class="logo-sm">
                                    <!--<img src="<?= base_url() ?>assets/images/logo-sm.png" alt="" height="22">-->
                                    <b style="color: #fff; font-size: 20px">E-ABSENSI</b>
                                </span>
                                <span class="logo-lg">
                                    <!--<img src="<?= base_url() ?>assets/images/logo-dark.png" alt="" height="20">-->
                                    <b style="color: #fff; font-size: 20px">E-ABSENSI</b>
                                </span>
                            </a>

                            <a href="<?= site_url('dashboard') ?>" class="logo logo-light">
                                <span class="logo-sm">
                                    <!--<img src="<?= base_url() ?>assets/images/logo-sm.png" alt="" height="22">-->
                                    <b style="color: #fff; font-size: 20px">E-ABSENSI</b>
                                </span>
                                <span class="logo-lg">
                                    <!--<img src="<?= base_url() ?>assets/images/logo-light.png" alt="" height="20">-->
                                    <b style="color: #fff; font-size: 20px">E-ABSENSI</b>
                                </span>
                            </a>
                        </div>

                        <button type="button" class="btn btn-sm px-3 font-size-16 d-lg-none header-item waves-effect waves-light" data-bs-toggle="collapse" data-bs-target="#topnav-menu-content">
                            <i class="fa fa-fw fa-bars"></i>
                        </button>

                    </div>

                    <div class="d-flex">

                        <div class="dropdown d-inline-block d-lg-none ms-2">
                            <button type="button" class="btn header-item noti-icon waves-effect" id="page-header-search-dropdown"
                                data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="uil-search"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right p-0"
                                aria-labelledby="page-header-search-dropdown">
                    
                                <form class="p-3">
                                    <div class="form-group m-0">
                                        <div class="input-group">
                                            <input type="text" class="form-control" placeholder="Search ..." aria-label="Recipient's username">
                                            <div class="input-group-append">
                                                <button class="btn btn-primary" type="submit"><i class="mdi mdi-magnify"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>


                        <div class="dropdown d-inline-block">
                            <button type="button" class="btn header-item waves-effect" id="page-header-user-dropdown"
                                data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <img class="rounded-circle header-profile-user" src="<?= base_url() ?>assets/images/users/<?= $this->ion_auth->user()->row()->photo ?>"
                                    alt="Header Avatar">
                                <span class="d-none d-xl-inline-block ms-1 fw-medium font-size-15"><?= $this->ion_auth->user()->row()->first_name ?></span>
                                <i class="dripicons-chevron-down d-none d-xl-inline-block font-size-15"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <!-- item-->
                                <!--<a class="dropdown-item" href="#"><i class="dripicons-user font-size-18 align-middle text-muted me-1"></i> <span class="align-middle">Lihat Profil</span></a>-->
                                <a class="dropdown-item" href="<?= site_url('logout') ?>"><i class="dripicons-power font-size-18 align-middle me-1 text-muted"></i> <span class="align-middle">Keluar</span></a>
                            </div>
                        </div>

                        <div class="dropdown d-inline-block">
                            <button type="button" class="btn header-item noti-icon right-bar-toggle waves-effect">
                                <i class="uil-cog"></i>
                            </button>
                        </div>
            
                    </div>
                </div>
                <div class="container-fluid">
                    <div class="topnav">

                        <nav class="navbar navbar-light navbar-expand-lg topnav-menu">
    
                            <div class="collapse navbar-collapse" id="topnav-menu-content">
                                <ul class="navbar-nav">

                                    <li class="nav-item">
                                        <a class="nav-link" href="<?= site_url('dashboard') ?>">
                                            <i class="dripicons-home me-2"></i>Dashboard
                                        </a>
                                    </li>

                                <?php $role = $this->ion_auth->get_users_groups()->row()->name; ?>

                                <?php if(in_array($role, ['admin', 'admin-branch'])){ ?>
                                    <li class="nav-item dropdown">
                                        <a class="nav-link dropdown-toggle arrow-none" href="#" id="topnav-pages" role="button">
                                            <i class="dripicons-inbox me-2"></i>Master Data <div class="arrow-down"></div>
                                        </a>
                                        <div class="dropdown-menu" aria-labelledby="topnav-pages">
                                        
                                        <?php if($role == 'admin'){ ?>
                                            <a href="<?= site_url('master_data/branch') ?>" class="dropdown-item"><i class="dripicons-store"></i> Cabang</a>
                                            <a href="<?= site_url('master_data/insentif') ?>" class="dropdown-item"><i class="dripicons-card"></i> Insentif</a>
                                            <a href="<?= site_url('master_data/deduction') ?>" class="dropdown-item"><i class="dripicons-card"></i> Pemotongan</a>
                                        <?php } ?>

                                            <div class="dropdown">
                                                <a class="dropdown-item dropdown-toggle arrow-none" href="#" id="topnav-email"
                                                    role="button">
                                                    <i class="dripicons-user-id"></i> Jabatan <div class="arrow-down"></div>
                                                </a>
                                                <div class="dropdown-menu" aria-labelledby="topnav-email">
                                                    <a href="<?= site_url('master_data/position') ?>" class="dropdown-item">Posisi</a>
                                                    <a href="<?= site_url('master_data/subdepartement') ?>" class="dropdown-item">Sub Departement</a>
                                                </div>
                                            </div>

                                            <a href="<?= site_url('master_data/employee') ?>" class="dropdown-item"><i class="dripicons-user"></i> Karyawan</a>
                                        </div>
                                    </li>
                                <?php } ?>

                                <?php if(in_array($role, ['admin', 'admin-branch', 'hr', 'employee', 'supervisor'])){ ?>
                                    <li class="nav-item dropdown">
                                        <a class="nav-link dropdown-toggle arrow-none" href="#" id="topnav-pages" role="button">
                                            <i class="dripicons-clock me-2"></i>Human Resources<div class="arrow-down"></div>
                                        </a>
                                        <div class="dropdown-menu" aria-labelledby="topnav-pages">
                                            <?php if(in_array($role, ['admin', 'admin-branch', 'supervisor'])){ ?>
                                                <div class="dropdown">
                                                    <a class="dropdown-item dropdown-toggle arrow-none" href="#" id="topnav-email"
                                                        role="button">
                                                        <i class="dripicons-clock"></i> Jadwal Kerja <div class="arrow-down"></div>
                                                    </a>
                                                    <div class="dropdown-menu" aria-labelledby="topnav-email">
                                                        <a href="<?= site_url('hr/shift') ?>" class="dropdown-item">Shift</a>
                                                        <!--<a href="<?= site_url('hr/cluster') ?>" class="dropdown-item">Jadwal Cluster</a>-->
                                                    </div>
                                                </div>
                                            <?php } ?>
                                            
                                            <?php if(in_array($role, ['admin', 'admin-branch', 'supervisor'])){ ?>
                                                <div class="dropdown">
                                                    <a class="dropdown-item dropdown-toggle arrow-none" href="#" id="topnav-email"
                                                        role="button">
                                                        <i class="dripicons-clock"></i> Lembur <div class="arrow-down"></div>
                                                    </a>
                                                    <div class="dropdown-menu" aria-labelledby="topnav-email">
                                                        <a href="<?= site_url('hr/overtime/list') ?>" class="dropdown-item">Pengajuan</a>
                                                        <a href="<?= site_url('hr/overtime/acc') ?>" class="dropdown-item">Acc</a>
                                                    </div>
                                                </div>

                                            <?php }else{ ?>
                                                 <a href="<?= site_url('hr/overtime/list') ?>" class="dropdown-item"><i class="dripicons-calendar"></i> Pengajuan Lembur</a>
                                            <?php } ?>
                                            
                                            <?php if(in_array($role, ['admin', 'admin-branch', 'employee'])){ ?>
                                                <div class="dropdown">
                                                    <a class="dropdown-item dropdown-toggle arrow-none" href="#" id="topnav-email"
                                                        role="button">
                                                        <i class="dripicons-clock"></i> Izin <div class="arrow-down"></div>
                                                    </a>
                                                    <div class="dropdown-menu" aria-labelledby="topnav-email">
                                                        <a href="<?= site_url('hr/leave/list') ?>" class="dropdown-item">Pengajuan</a>

                                                        <?php if(in_array($role, ['admin', 'admin-branch'])){ ?>
                                                            <a href="<?= site_url('hr/leave/acc') ?>" class="dropdown-item">Acc</a>
                                                        <?php } ?>
                                                        
                                                    </div>
                                                </div>

                                            <?php }else{ ?>
                                                 <a href="<?= site_url('hr/leave/list') ?>" class="dropdown-item"><i class="dripicons-calendar"></i> Pengajuan Izin</a>
                                            <?php } ?>

                                            <a href="<?= site_url('hr/presence') ?>" class="dropdown-item"><i class="dripicons-calendar"></i> Presensi</a>
                                            <?php if(in_array($role, ['admin', 'admin-branch', 'hr', 'supervisor'])){ ?>
                                                <?php
                                                    $absen_report_url = '#';
                                                    $absen_report_files = glob(FCPATH.'exports/report_absen_*_all.xlsx');
                                                    if(!empty($absen_report_files)){
                                                        usort($absen_report_files, function($a, $b){
                                                            return filemtime($b) - filemtime($a);
                                                        });
                                                        $absen_report_url = base_url('exports/'.basename($absen_report_files[0]));
                                                    }
                                                ?>
                                                <a href="<?= $absen_report_url ?>" target="_blank" class="dropdown-item"><i class="mdi mdi-file-excel"></i> Report Performance Absen</a>
                                            <?php } ?>

                                            <a href="<?= site_url('hr/payroll') ?>" class="dropdown-item"><i class="dripicons-wallet"></i> Penggajian</a>
                                        </div>
                                    </li>
                                <?php } ?>

                                <?php if(in_array($role, ['admin', 'admin-branch'])){ ?>
                                    <li class="nav-item dropdown">
                                        <a class="nav-link dropdown-toggle arrow-none" href="#" id="topnav-wa" role="button">
                                            <i class="mdi mdi-whatsapp me-2" style="color:#25D366"></i>WA Agent<div class="arrow-down"></div>
                                        </a>
                                        <div class="dropdown-menu" aria-labelledby="topnav-wa">
                                            <a href="<?= site_url('wa') ?>" class="dropdown-item"><i class="mdi mdi-view-dashboard"></i> Dashboard</a>
                                            <a href="<?= site_url('wa/config') ?>" class="dropdown-item"><i class="mdi mdi-cog"></i> Konfigurasi</a>
                                            <a href="<?= site_url('wa/logs') ?>" class="dropdown-item"><i class="mdi mdi-history"></i> Log Pengiriman</a>
                                        </div>
                                    </li>
                                <?php } ?>

                                <?php if(in_array($role, ['admin', 'admin-branch'])){ ?>
                                    <li class="nav-item dropdown">
                                        <a class="nav-link dropdown-toggle arrow-none" href="#" id="topnav-sync" role="button">
                                            <i class="mdi mdi-sync me-2"></i>Data Sync<div class="arrow-down"></div>
                                        </a>
                                        <div class="dropdown-menu" aria-labelledby="topnav-sync">
                                            <a href="<?= site_url('sync') ?>" class="dropdown-item"><i class="mdi mdi-server"></i> Mesin Absensi</a>
                                            <a href="<?= site_url('attendance') ?>" class="dropdown-item"><i class="mdi mdi-account-check"></i> Data Kehadiran</a>
                                            <a href="<?= site_url('attendance/daily_report') ?>" class="dropdown-item"><i class="mdi mdi-calendar-check"></i> Rekap Absensi Harian</a>
                                            <a href="<?= site_url('attendance/machine_report') ?>" class="dropdown-item"><i class="mdi mdi-fingerprint"></i> Report Absen Mesin</a>
                                            <a href="<?= site_url('attendance/early_leave_report') ?>" class="dropdown-item"><i class="mdi mdi-clock-alert-outline"></i> Rekap Izin Pulang Cepat</a>
                                        </div>
                                    </li>
                                <?php } ?>

                                    <!--<li class="nav-item dropdown">
                                        <a class="nav-link dropdown-toggle arrow-none" href="#" id="topnav-pages" role="button">
                                            <i class="dripicons-document me-2"></i>Laporan<div class="arrow-down"></div>
                                        </a>
                                        <div class="dropdown-menu" aria-labelledby="topnav-pages">

                                        <?php if(in_array($role, ['admin', 'admin-branch', 'finance'])){ ?>
                                            <a href="<?= site_url('report/cashflow') ?>" class="dropdown-item"><i class="dripicons-experiment"></i> Cashflow</a>

                                            <a href="<?= site_url('report/profit_loss') ?>" class="dropdown-item"><i class="dripicons-experiment"></i> Laba Rugi</a>
                                        <?php } ?>

                                        <?php if(in_array($role, ['admin', 'admin-branch', 'inventory'])){ ?>
                                            <a href="<?= site_url('report/asset') ?>" class="dropdown-item"><i class="dripicons-experiment"></i> Asset</a>
                                        <?php } ?>

                                        </div>
                                    </li>-->

                                </ul>
                            </div>
                        </nav>
                    </div>
                </div>
            </header>
    


            <!-- ============================================================== -->
            <!-- Start right Content here -->
            <!-- ============================================================== -->
            <div class="main-content">

                <div class="page-content">
                    <div class="container-fluid">

                        <?= $contents ?>
                        
                    </div> <!-- container-fluid -->
                </div>
                <!-- End Page-content -->

                
                <footer class="footer">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col-sm-6">
                                <script>document.write(new Date().getFullYear())</script> © Timesheet App.
                            </div>
                            <div class="col-sm-6">
                                <div class="text-sm-end d-none d-sm-block">
                                    <u>by <a href="https://waderjhonson.com/" target="_blank" class="text-reset">Wader Jhonson</a></u>
                                </div>
                            </div>
                        </div>
                    </div>
                </footer>
            </div>
            <!-- end main content-->

        </div>
        <!-- END layout-wrapper -->

        <!-- Right Sidebar -->
        <div class="right-bar">
            <div data-simplebar class="h-100">

                <div class="rightbar-title d-flex align-items-center px-3 py-4">
            
                    <h5 class="m-0 me-2">Settings</h5>

                    <a href="javascript:void(0);" class="right-bar-toggle ms-auto">
                        <i class="mdi mdi-close noti-icon"></i>
                    </a>
                </div>



                <!-- Settings -->
                <hr class="mt-0" />
                <h6 class="text-center mb-0">Choose Layouts</h6>

                <div class="p-4">
                    <div class="mb-2">
                        <img src="<?= base_url() ?>assets/images/layouts/layout-1.jpg" class="img-fluid img-thumbnail" alt="">
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input type="checkbox" class="form-check-input theme-choice" id="light-mode-switch" checked />
                        <label class="form-check-label" for="light-mode-switch">Light Mode</label>
                    </div>
    
                    <div class="mb-2">
                        <img src="<?= base_url() ?>assets/images/layouts/layout-2.jpg" class="img-fluid img-thumbnail" alt="">
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input type="checkbox" class="form-check-input theme-choice" id="dark-mode-switch" data-bsStyle="<?= base_url() ?>assets/css/bootstrap-dark.min.css" data-appStyle="<?= base_url() ?>assets/css/app-dark.min.css" />
                        <label class="form-check-label" for="dark-mode-switch">Dark Mode</label>
                    </div>
    
                    <div class="mb-2">
                        <img src="<?= base_url() ?>assets/images/layouts/layout-3.jpg" class="img-fluid img-thumbnail" alt="">
                    </div>
                    <div class="form-check form-switch mb-5">
                        <input type="checkbox" class="form-check-input theme-choice" id="rtl-mode-switch" data-appStyle="<?= base_url() ?>assets/css/app-rtl.min.css" />
                        <label class="form-check-label" for="rtl-mode-switch">RTL Mode</label>
                    </div>

            
                </div>

            </div> <!-- end slimscroll-menu-->
        </div>
        <!-- /Right-bar -->

        <!-- Right bar overlay-->
        <div class="rightbar-overlay"></div>

        <!-- JAVASCRIPT -->

        <div class="modal fade" id="modal-info" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" role="dialog" aria-labelledby="staticBackdropLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title" id="staticBackdropLabel" style="color: #fff">INFORMASI</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-3 text-center">
                                <img src="<?= base_url('assets/images/icon/megaphone.png') ?>" style="width: 50px">
                            </div>
                            <div class="col-md-9">
                                <h6>Info</h6>
                                <div id="modal-info-msg"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>


        <div class="modal fade" id="modal-success" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" role="dialog" aria-labelledby="staticBackdropLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header bg-success">
                        <h5 class="modal-title" id="staticBackdropLabel" style="color: #fff">BERHASIL</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-3">
                                <img src="<?= base_url('assets/images/icon/success.png') ?>" class="img-fluid">
                            </div>
                            <div class="col-md-9">
                                <h6>Info</h6>
                                <div id="modal-success-msg"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>
        
        <script src="<?= base_url() ?>assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
        <script src="<?= base_url() ?>assets/libs/metismenu/metisMenu.min.js"></script>
        <script src="<?= base_url() ?>assets/libs/simplebar/simplebar.min.js"></script>
        <script src="<?= base_url() ?>assets/libs/node-waves/waves.min.js"></script>
        <script src="<?= base_url() ?>assets/libs/waypoints/lib/jquery.waypoints.min.js"></script>
        <script src="<?= base_url() ?>assets/libs/jquery.counterup/jquery.counterup.min.js"></script>

        <script src="<?= base_url() ?>assets/libs/moment/min/moment.min.js"></script>
        <script src="<?= base_url() ?>assets/libs/timepicker/bootstrap-material-datetimepicker.js"></script>
        <script src="<?= base_url() ?>assets/libs/sweetalert2/sweetalert2.min.js"></script>

        <script src="<?= base_url() ?>assets/libs/jquery.repeater/jquery.repeater.min.js"></script>
        <script src="<?= base_url() ?>assets/js/pages/form-repeater.int.js"></script>
        <script src="<?= base_url() ?>assets/js/apps.js"></script>
        
        <script type="text/javascript">
            $('.select-plugin').select2({
                height : 50
            });
            
            function show_loading(){
                return '<div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>';
            }

            function show_modal(type, msg){
                $('#modal-'+ type).modal('show');
                $('#modal-'+ type + '-msg').html(msg);
            }

            $(".mdate-max").bootstrapMaterialDatePicker({
                weekStart: 0,
                maxDate : "<?= date('Y-m-d') ?>",
                time : false,
                lang : 'tr'
            });

            $(".mdate").bootstrapMaterialDatePicker({
                weekStart: 0,
                time : false,
                lang : 'tr'
            });

            var param = ['subuh', 'dzuhur', 'ashar', 'maghrib', 'isha', 'friday'];
            for (var i = param.length - 1; i >= 0; i--){
                $('#e_'+param[i]+'_pray_time').timepicker({mode : '24hr'});
                $('#e_'+param[i]+'_pray_time_in').timepicker({mode : '24hr'});
                $('#e_'+param[i]+'_pray_time_out').timepicker({mode : '24hr'});
                $('#'+param[i]+'_time_in').timepicker({mode : '24hr'});
                $('#'+param[i]+'_time_out').timepicker({mode : '24hr'});
            }

            $('#entry_time').timepicker({
                mode : '24hr'
            });

            $('#out_time').timepicker({
                mode : '24hr'
            });

            $('#rest_time_in').timepicker({
                mode : '24hr'
            });

            $('#rest_time_out').timepicker({
                mode : '24hr'
            });

            $('#timepicker1').timepicker({
                mode : '24hr'
            });

            $('#timepicker2').timepicker({
                mode : '24hr'
            });

            $('#timepicker3').timepicker({
                mode : '24hr'
            });

            $('#timepicker4').timepicker({
                mode : '24hr'
            });

            $('#timepicker5').timepicker({
                mode : '24hr'
            });

            $('#timepicker6').timepicker({
                mode : '24hr'
            });

            $('#timepicker7').timepicker({
                mode : '24hr'
            });

            $('#timepicker8').timepicker({
                mode : '24hr'
            });

            $('#timepicker9').timepicker({
                mode : '24hr'
            });

            $('#timepicker10').timepicker({
                mode : '24hr'
            });

            $('#timepicker11').timepicker({
                mode : '24hr'
            });

            $('#timepicker12').timepicker({
                mode : '24hr'
            });

            $('#timepicker13').timepicker({
                mode : '24hr'
            });

            $('#timepicker14').timepicker({
                mode : '24hr'
            });

            $('#timepicker15').timepicker({
                mode : '24hr'
            });

            $('#timepicker16').timepicker({
                mode : '24hr'
            });

            $('#timepicker17').timepicker({
                mode : '24hr'
            });

            $('#timepicker18').timepicker({
                mode : '24hr'
            });

            $('#start_time_rest').timepicker({
                mode : '24hr'
            });

            $('#end_time_rest').timepicker({
                mode : '24hr'
            });

            $('#start_time_rest_friday').timepicker({
                mode : '24hr'
            });

            $('#end_time_rest_friday').timepicker({
                mode : '24hr'
            });

            $('#start_time_late').timepicker({
                mode : '24hr'
            });

            $('#start_time').timepicker({
                mode : '24hr'
            });

            $('#end_time').timepicker({
                mode : '24hr'
            });

            $('#start_time_in').timepicker({
                mode : '24hr'
            });

            $('#start_time_out').timepicker({
                mode : '24hr'
            });

            $('#end_time_in').timepicker({
                mode : '24hr'
            });

            $('#end_time_out').timepicker({
                mode : '24hr'
            });

             $(".mdate-current").bootstrapMaterialDatePicker({
                weekStart: 0,
                maxDate : "<?= date('Y-m-d') ?>",
                minDate : "<?= date('Y-m-01') ?>",
                time : false,
                lang : 'tr'
            });

            $(".datatable").DataTable();

            var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
              return new bootstrap.Popover(popoverTriggerEl)
            });

        </script>
        <!-- apexcharts -->

    </body>

</html>
