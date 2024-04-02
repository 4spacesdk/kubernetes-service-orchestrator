<?php

?>
<html>
<head>
    <title>KSO | Login</title>

    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css"
          integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO" crossorigin="anonymous">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>

    <link rel="stylesheet" href="https://pro.fontawesome.com/releases/v5.5.0/css/all.css"
          media="print" onload="this.media='all'"
          lazyload
          integrity="sha384-j8y0ITrvFafF4EkV1mPW0BKm6dp3c+J9Fky22Man50Ofxo2wNe5pT1oZejDH9/Dt" crossorigin="anonymous">

    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"
            integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo"
            crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js"
            integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49"
            crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js"
            integrity="sha384-ChfqqxuZUCnJSK3+MXmPNIyE6ZbWh2IMqE241rYiqJxyMiZ6OW/JmZQ5stwEULTy"
            crossorigin="anonymous"></script>

    <style>
        /* Added */
        .has-float-label {
            display: block;
            position: relative;
        }

        a.btn.btn-lg{
            color: #fff!important;
        }
        .has-float-label label,
        .has-float-label > span {
            color: grey;
            position: absolute;
            left: 0;
            top: 0;
            cursor: text;
            font-size: 120%;
            opacity: 1;
            -webkit-transition: all .3s;
            transition: all .3s;
        }

        .has-float-label select {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }

        .has-float-label textarea {
            width: 100%;
        }

        .has-float-label input,
        .has-float-label select,
        .has-float-label textarea {
            font-size: 15px;
            font-weight: normal !important;
            padding-top: 1.3em;
            margin-bottom: 2px;
            border: 0;
            height: 45px;
            border-radius: 0;
            border-bottom: 2px solid rgba(0, 0, 0, .1);
        }

        .has-float-label input::-webkit-input-placeholder,
        .has-float-label select::-webkit-input-placeholder,
        .has-float-label textarea::-webkit-input-placeholder {
            opacity: 1;
            -webkit-transition: all .2s;
            transition: all .2s;
        }

        .has-float-label input::-moz-placeholder,
        .has-float-label select::-moz-placeholder,
        .has-float-label textarea::-moz-placeholder {
            opacity: 1;
            transition: all .2s;
        }

        .has-float-label input:-ms-input-placeholder,
        .has-float-label select:-ms-input-placeholder,
        .has-float-label textarea:-ms-input-placeholder {
            opacity: 1;
            transition: all .2s;
        }

        .has-float-label input::placeholder,
        .has-float-label select::placeholder,
        .has-float-label textarea::placeholder {
            opacity: 1;
            -webkit-transition: all .2s;
            transition: all .2s;
        }

        .has-float-label input:invalid:not(:focus)::-webkit-input-placeholder,
        .has-float-label select:invalid:not(:focus)::-webkit-input-placeholder,
        .has-float-label textarea:invalid:not(:focus)::-webkit-input-placeholder {
            opacity: 0;
        }

        .has-float-label input:invalid:not(:focus)::-moz-placeholder,
        .has-float-label select:invalid:not(:focus)::-moz-placeholder,
        .has-float-label textarea:invalid:not(:focus)::-moz-placeholder {
            opacity: 0;
        }

        .has-float-label input:invalid:not(:focus):-ms-input-placeholder,
        .has-float-label select:invalid:not(:focus):-ms-input-placeholder,
        .has-float-label textarea:invalid:not(:focus):-ms-input-placeholder {
            opacity: 0;
        }

        .has-float-label input:invalid:not(:focus)::placeholder,
        .has-float-label select:invalid:not(:focus)::placeholder,
        .has-float-label textarea:invalid:not(:focus)::placeholder {
            opacity: 0;
        }

        .has-float-label input:invalid:not(:focus) + *,
        .has-float-label select:invalid:not(:focus) + *,
        .has-float-label textarea:invalid:not(:focus) + * {
            font-size: 140%;
            opacity: .5;
            top: 1.3em;
        }

        .has-float-label input:focus,
        .has-float-label select:focus,
        .has-float-label textarea:focus {
            outline: 0;
            border-color: #4285f4;
        }


        /*  Added End */

        .form-control {
            height: auto;
        }

        :root {
            --input-padding-x: 1.5rem;
            --input-padding-y: .75rem;
        }

        body {
            background: #193b46;
            /* fallback for old browsers */
            background: -webkit-linear-gradient(to top, #193b46, #152b36);
            /* Chrome 10-25, Safari 5.1-6 */
            background: linear-gradient(to top, #193b46, #152b36);
            /* W3C, IE 10+/ Edge, Firefox 16+, Chrome 26+, Opera 12+, Safari 7+ */
        }

        .card-signin {
            border: 0;
            border-radius: .5rem;
            box-shadow: 0 0.5rem 1rem 0 rgba(0, 0, 0, 0.1);
        }

        .card-signin .card-title {
            margin-bottom: 2rem;
            font-weight: 300;
            font-size: 1.5rem;
        }

        .card-signin .card-body {
            padding: 2rem;
        }

        .form-signin {
            width: 100%;
        }

        .btn {
            font-size: 80%;
            border-radius:, 5rem;
            letter-spacing: .1rem;
            font-weight: bold;
            padding: 1rem;
            transition: all 0.2s;
        }

        .btn.btn-primary {
            background: #193b46;
            border-color: #193b46;
        }

        .btn.btn-warning {
            background: #464646;
            border-color: #464646;
        }

        a,
        a:link,
        .text-primary {
            color: #193b46 !important;
        }

        .form-label-group {
            position: relative;
            margin-bottom: 1rem;
        }

        .form-label-group input {
            border-radius: .5rem;
        }

        .form-label-group > input,
        .form-label-group > label {
            padding: var(--input-padding-y) var(--input-padding-x);
        }

        .form-label-group > label {
            position: absolute;
            top: 0;
            left: 0;
            display: block;
            width: 100%;
            margin-bottom: 0;
            /* Override default `<label>` margin */
            line-height: 1.5;
            color: #495057;
            border: 1px solid transparent;
            border-radius: .25rem;
            transition: all .1s ease-in-out;
        }

        .form-label-group input::-webkit-input-placeholder {
            color: transparent;
        }

        .form-label-group input:-ms-input-placeholder {
            color: transparent;
        }

        .form-label-group input::-ms-input-placeholder {
            color: transparent;
        }

        .form-label-group input::-moz-placeholder {
            color: transparent;
        }

        .form-label-group input::placeholder {
            color: transparent;
        }

        .form-label-group input:not(:placeholder-shown) {
            padding-top: calc(var(--input-padding-y) + var(--input-padding-y) * (2 / 3));
            padding-bottom: calc(var(--input-padding-y) / 3);
        }

        .form-label-group input:not(:placeholder-shown) ~ label {
            padding-top: calc(var(--input-padding-y) / 3);
            padding-bottom: calc(var(--input-padding-y) / 3);
            font-size: 12px;
            color: #777;
        }

        .copyright {
            transform: translate(0px, 20px);
            transition: all .2s ease-out;
            opacity: .75;
            font-size: 70%;
            cursor: default;
        }

        .copyright:hover {
            opacity: 1;
        }

        .logo {
            position: fixed;
            bottom: 24px;
            right: 24px;
        }

        .logo-header {
            font-size: 20px;
            color: #ffffff;
            float: left;
            text-transform: uppercase;
            content: ' ';
            background: url(/admin_assets/img/logo-header-blue.svg);

            background-repeat: no-repeat;
            background-size: auto 100%;
            /* background-position: 103px 0px; */
            width: 100%;
            height: 60px;
            display: block;
            opacity: 1;
            margin: 0 !important;
            background-position: center center;
            background-size: contain;
        }

        .btn-secondary a, .btn-secondary a:hover {
            color: white;
        }

        .hidden {
            display: none !important;
        }

    </style>
</head>

<body>

<div class="container">
    <div class="row">
        <div class="col-sm-9 col-md-7 col-lg-5 mx-auto">
            <div class="card card-signin my-5">
                <div class="card-body">
                    <h5 class="card-title text-center"><i class="far fa-badge-check -fa-2x -align-middle text-primary"></i> Password renewal</h5>
                    <div class="alert alert-warning clearfix <?= isset(DebugTool\Data::getStore()['description']) ? '' : '' ?>" role="alert" id="validation">
                        <p><?= isset(DebugTool\Data::getStore()['description']) ? DebugTool\Data::getStore()['description']: '' ?></p>

                        <div  id="pswd_info_b"  class="pswd_info">
                            <p>Password requirement</p>
                            <ul>
                                <li id="letter" class="invalid">At least <strong>one letter</strong></li>
                                <li id="capital" class="invalid">At least <strong>one uppercase letter</strong></li>
                                <li id="number" class="invalid">At least <strong>one number</strong></li>
                                <li id="length" class="invalid">At least <strong>eight characters</strong></li>
                                <li id="same" class="invalid">Must be identical</li>
                            </ul>
                        </div>
                    </div>

                    <form class="form-signin" method="post">
                        <div class="form-label-group">
                            <input type="password" id="password" name="password" class="form-control" placeholder="Password" required autofocus>
                            <label for="password">Password</label>
                        </div>

                        <div class="form-label-group">
                            <input type="password" id="password_confirm" name="password_confirm" class="form-control" placeholder="Repeat password" required>
                            <label for="password_confirm">Repeat password</label>
                        </div>

                        <?php if(isset(DebugTool\Data::getStore()['description'])) { ?>
                        <div class="alert alert-warning" role="alert"><?=DebugTool\Data::getStore()['description']?></div>
                        <?php } ?>

                        <button class="btn btn-lg btn-primary btn-block text-uppercase" type="submit">Save</button>
                        
                        <div class="text-muted small text-center copyright">Copyright © 2018-<?= date('Y') ?> ·
                            <a href="https://4spaces.dk" target="_blank"> 4 Spaces ApS</a>
                        </div>

                    </form>


                </div>
            </div>
            <!--
            <a href="https://www.4spaces.dk" target="_blank" class="logo"><img _ngcontent-c3="" alt="4 Spaces" src="https://www.4spaces.dk/assets/four-spaces-white.svg" width="16"></a>
            -->
        </div>
    </div>
</div>

<script>

    function validateLocalPassword(target) {
        var pswd = target.val();

        var valid = true;
        //validate the length

        if($('input[name="password"]').val() != $('input[name="password_confirm"]').val()) {
            $('#same').removeClass('valid').addClass('invalid');
            valid = false;
        } else {
            $('#same').addClass('valid').removeClass('invalid');
        }

        if( pswd.length < 8 ) {
            $('#length').removeClass('valid').addClass('invalid');
            valid = false;
        } else {
            $('#length').removeClass('invalid').addClass('valid');
        }
        //validate letter
        if ( pswd.match(/[A-z]/) ) {
            $('#letter').removeClass('invalid').addClass('valid');

        } else {
            $('#letter').removeClass('valid').addClass('invalid');
            valid = false;
        }

        // validate capital letter
        if ( pswd.match(/[A-Z]/) ) {
            $('#capital').removeClass('invalid').addClass('valid');
        } else {
            $('#capital').removeClass('valid').addClass('invalid');
            valid = false;
        }

        // validate number
        if ( pswd.match(/\d/) ) {
            $('#number').removeClass('invalid').addClass('valid');
        } else {
            $('#number').removeClass('valid').addClass('invalid');
            valid = false;
        }

        /*
        if(valid)
            $('#validation').addClass('hidden')
        else
            $('#validation').removeClass('hidden')
            */

        return valid;
    }

    $(function() {

        // Password validation

        $('input[name=password]').keyup(function(){
            $('#password-error').remove();
            validateLocalPassword($(this));

        }).focus(function() {
        }).blur(function() {
        });

        $('input[name=password_confirm]').keyup(function(){
            validateLocalPassword($(this));
        }).focus(function() {
        }).blur(function() {
        });

        $('form').on('submit', function(e) {
            var valid = validateLocalPassword($('input[name=password]'));
            console.warn('valid:',valid)
            if(!valid){
                if((!$('input[name=password]').val() && !$('input[name=password_confirm]').val())){
                    console.log('no passwords');
                }else {
                    $('#pswd_info_a').show();
                    e.preventDefault();
                }
            }
        })

    });


</script>

</body>
</html>
