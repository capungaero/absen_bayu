<!doctype html>
<html lang="en">

<head>
        
        <meta charset="utf-8" />
        <title>Timesheet App</title>
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

    <body>

        <!-- Begin page -->
            <!-- ============================================================== -->
            <!-- Start right Content here -->
            <!-- ============================================================== -->

                <div class="mt-5">
                    <div class="container-fluid">

                        <?= $contents ?>
                        
                    </div> <!-- container-fluid -->
                </div>
                <!-- End Page-content -->
            <!-- end main content-->

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
