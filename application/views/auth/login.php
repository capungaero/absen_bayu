<!doctype html>
<html lang="en">

    
<head>
        
        <meta charset="utf-8" />
        <title>TimeSheet App</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta content="Aplikasi manajemen absensi" name="description" />
        <meta content="TimeSheet App" name="author" />
        <!-- App favicon -->
        <link rel="shortcut icon" href="<?= base_url() ?>assets/images/favicon.ico">

        <!-- Bootstrap Css -->
        <link href="<?= base_url() ?>assets/css/bootstrap.min.css" id="bootstrap-style" rel="stylesheet" type="text/css" />
        <!-- Icons Css -->
        <link href="<?= base_url() ?>assets/css/icons.min.css" rel="stylesheet" type="text/css" />
        <!-- App Css-->
        <link href="<?= base_url() ?>assets/css/app.min.css" id="app-style" rel="stylesheet" type="text/css" />
        <script src="<?= base_url() ?>assets/libs/jquery/jquery.min.js"></script>

    </head>

    <body class="authentication-bg">

        <div class="home-btn d-none d-sm-block">
            <a href="<?= site_url() ?>" class="text-dark"><i class="mdi mdi-home-variant h2"></i></a>
        </div>
        <div class="account-pages my-5 pt-sm-5">
            <div class="container">
                <div class="row align-items-center justify-content-center">
                    <div class="col-md-8 col-lg-6 col-xl-5">
                        <div class="card">
                           
                            <div class="card-body p-4"> 
                                <div class="row">
                                    <div class="col-6">
                                        <!--<img src="<?= base_url('assets/images/logo_2.png') ?>" alt="" style="width:100px">-->
                                    </div>
                                    <div class="col-6 text-end">
                                        <!--<img src="<?= base_url('assets/images/logo.png') ?>" alt="" style="width:115px">-->
                                    </div>
                                </div>

                                <div class="row mt-3">
                                    <div class="col-lg-12">
                                        <div class="text-center">
                                            <a href="<?= site_url() ?>" class="">
                                                <h2>E-ABSENSI</h2> 
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <div class="p-2">

                                    <?= $this->session->flashdata('alert_message') ?>
                                    
                                    <form class="form-horizontal auth-form" method="POST" action="<?= site_url('do_login') ?>">
                                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">
        
                                        <div class="mb-3">
                                            <label class="form-label" for="username">Email</label>
                                            <input type="text" class="form-control" id="email" placeholder="Masukkan Email" required="" name="email">
                                        </div>
                
                                        <div class="mb-3">
                                            <!--<div class="float-end">
                                                <a href="<?= site_url('authentication/forget') ?>" class="text-muted">Lupa Password</a>
                                            </div>-->
                                            <label class="form-label" for="userpassword">Password</label>
                                            <input type="password" class="form-control" id="password" placeholder="Masukkan password" required="" name="password">
                                        </div>
                                        
                                        <div class="mt-3 text-end">
                                            <button class="btn btn-primary w-sm waves-effect waves-light" type="submit">Log In</button>
                                        </div>

                                    </form>
                                </div>
            
                            </div>
                        </div>

                        <div class="mt-5 text-center">
                            <p>© <script>document.write(new Date().getFullYear())</script> Timesheet App <u><a href="https://waderjhonson.com/" target="_blank" class="text-reset">by Wader Jhonson</a></u></p>
                        </div>

                    </div>
                </div>
                <!-- end row -->
            </div>
            <!-- end container -->
        </div>

        <!-- JAVASCRIPT -->
        
        <script src="<?= base_url() ?>assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
        <script src="<?= base_url() ?>assets/libs/metismenu/metisMenu.min.js"></script>
        <script src="<?= base_url() ?>assets/libs/simplebar/simplebar.min.js"></script>
        <script src="<?= base_url() ?>assets/libs/node-waves/waves.min.js"></script>
        <script src="<?= base_url() ?>assets/libs/waypoints/lib/jquery.waypoints.min.js"></script>
        <script src="<?= base_url() ?>assets/libs/jquery.counterup/jquery.counterup.min.js"></script>

        <script src="<?= base_url() ?>assets/js/app.js"></script>

    </body>
</html>
