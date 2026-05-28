<div class="row vh-100 d-flex justify-content-center">
    <div class="col-12 align-self-center">
        <div class="row">
            <div class="col-lg-5 mx-auto">
                <div class="card">
                    <div class="card-body p-0 auth-header-box">
                        <div class="text-center p-3">
                            <a href="index.html" class="logo logo-admin">
                                <img src="<?= base_url() ?>assets/images/logo-box.png" height="100" alt="logo" class="auth-logo">
                            </a> 
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <ul class="nav-border nav nav-pills" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active font-weight-semibold" data-toggle="tab" href="#LogIn_Tab" role="tab">Daftar</a>
                            </li>
                        </ul>
                         <!-- Tab panes -->
                        <div class="tab-content">
                            <div class="tab-pane active p-3" id="LogIn_Tab" role="tabpanel">     

                                <?= $this->session->flashdata('alert_message') ?>

                                <form class="form-horizontal auth-form" method="POST" action="<?= site_url('do_register') ?>">
                                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">
                                    <div class="form-group mb-2">
                                        <label for="username">Nama Lengkap</label>
                                        <div class="input-group">                                                                                         
                                            <input type="text" class="form-control" name="first_name" id="first_name" required="" autocomplete="off" placeholder="Masukkan nama lengkap kamu">
                                        </div>                                    
                                    </div>

                                    <div class="form-group mb-2">
                                        <label for="username">Email</label>
                                        <div class="input-group">                                                                                         
                                            <input type="email" class="form-control" name="email" id="email" required="" placeholder="Masukkan email">
                                        </div>                                    
                                    </div>

                                    <div class="form-group mb-2">
                                        <label for="username">Nomor Handphone</label>
                                        <div class="input-group">                                                                                         
                                            <input type="text" class="form-control" name="phone" id="phone" required="" placeholder="Masukkan nomor handphone">
                                        </div>                                    
                                    </div>
        
                                    <div class="form-group mb-2">
                                        <label for="userpassword">Password</label>                                            
                                        <div class="input-group">                                  
                                            <input type="password" class="form-control" name="password" id="userpassword" placeholder="Masukkan password" required="">
                                        </div>                               
                                    </div><!--end form-group--> 
        
                                    <div class="form-group mb-0 row">
                                        <div class="col-12">
                                            <button class="btn btn-primary btn-block waves-effect waves-light">Daftar <i class="fas fa-sign-in-alt ml-1"></i></button>
                                        </div><!--end col--> 
                                    </div> <!--end form-group-->                           
                                </form><!--end form-->
                                <div class="m-3 text-center text-muted">
                                    <p class="mb-0">Sudah punya akun ?  <a href="<?= site_url('authentication/login') ?>" class="btn btn-soft-primary ml-2"><b>Login Disini</b></a></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div><!--end card-->
            </div><!--end col-->
        </div><!--end row-->
    </div><!--end col-->
</div><!--end row-->
