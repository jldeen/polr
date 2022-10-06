@extends('layouts.base')

@section('css')
<link rel='stylesheet' href='css/login.css' />
@endsection

@section('content')
<div class="center-text">
    <h1>Login</h1><br/><br/>
    <div class="col-md-3"></div>
    <div class="col-md-6">
        <form action="login" method="POST">
            <input id="userNameInput" class="form-control login-field" type="text" placeholder="Email"/>
            <input id="passwordInput" class="form-control login-field" type="password" placeholder="Password"/>
            <input type="hidden" name='_token' value='{{csrf_token()}}' />
            <input id="logInButton" class="login-submit btn btn-success" type="Button" value="Login" onclick="logIn()"></br>
            <input id="logOutButton" class="login-submit btn btn-success" type="Button" value="Log Out" onclick="logOut()" style="display:none">

            <div id="log" style="width: 500px; height: 300px;"></div>

            <p class='login-prompts'>
            @if (env('POLR_ALLOW_ACCT_CREATION') == true)
                <small>Don't have an account? <a href='{{route('signup')}}'>Register</a></small>
            @endif

            @if (env('SETTING_PASSWORD_RECOV') == true)
                <small>Forgot your password? <a href='{{route('lost_password')}}'>Reset</a></small>
            @endif
            </p>
        </form>
    </div>
    <div class="col-md-3"></div>
</div>
@endsection


@section('js')
<script src="/js/jquery.min.js"></script>	
<script src="/js/aws-sdk-2.487.0.min.js"></script>
<script src="/js/aws-cognito-sdk.min.js"></script>
<script src="/js/amazon-cognito-identity.min.js"></script>

<script>
    //=============== AWS IDs ===============
    var userPoolId = 'us-east-1_OuS68Cxfl';
    var clientId = 'oam0d9r3q22h6fjl13c7cn3a7';
    var region = 'us-east-1';
    var identityPoolId = 'us-east-1:6451a891-7046-454f-a8a2-20d2fd794957';
    //=============== AWS IDs ===============

    var cognitoUser;
    var idToken;
    var userPool;
    
    var poolData = { 
        UserPoolId : userPoolId,
        ClientId : clientId
    };
    
    getCurrentLoggedInSession();

    function switchToVerificationCodeView(){
        $("#userNameInput").hide();
        $("#passwordInput").hide();
        $("#confirmPasswordInput").hide();
        $("#logInButton").hide();
        $("#registerButton").hide();
        $("#verificationCodeInput").show();
        $("#verifyCodeButton").show();
        $("#logOutButton").hide();
    }

    function switchToRegisterView(){
        $("#userNameInput").show();
        $("#passwordInput").show();
        $("#confirmPasswordInput").show();
        $("#logInButton").hide();
        $("#registerButton").show();
        $("#verificationCodeInput").hide();
        $("#verifyCodeButton").hide();
        $("#logOutButton").hide();
    }

    function switchToLogInView(){
        $("#userNameInput").val('');
        $("#passwordInput").val('');
        $("#userNameInput").show();
        $("#passwordInput").show();
        $("#confirmPasswordInput").hide();
        $("#logInButton").show();
        $("#registerButton").show();
        $("#verificationCodeInput").hide();
        $("#verifyCodeButton").hide();
        $("#logOutButton").hide();
    }

    function switchToLoggedInView(){
        $("#userNameInput").hide();
        $("#passwordInput").hide();
        $("#confirmPasswordInput").hide();
        $("#logInButton").hide();
        $("#registerButton").hide();
        $("#verificationCodeInput").hide();
        $("#verifyCodeButton").hide();
        $("#logOutButton").show();
    }

    function clearLogs(){
        $('#log').empty();
    }

    /*
    Starting point for user logout flow
    */
    function logOut(){
        if (cognitoUser != null) {

            $("#loader").show();
            cognitoUser.signOut();
            // switchToLogInView();
            logMessage('Logged out!');
            $("#loader").hide();
        }
    }

    /*
    Starting point for user login flow with input validation
    */
    function logIn(){

        if(!$('#userNameInput').val() || !$('#passwordInput').val()){
            logMessage('Please enter Username and Password!');
        }else{
            clearLogs();
            var authenticationData = {
                Username : $('#userNameInput').val(),
                Password : $("#passwordInput").val(),
            };
            var authenticationDetails = new AmazonCognitoIdentity.AuthenticationDetails(authenticationData);

            var userData = {
                Username : $('#userNameInput').val(),
                Pool : userPool
            };
            cognitoUser = new AmazonCognitoIdentity.CognitoUser(userData);

            $("#loader").show();
            cognitoUser.authenticateUser(authenticationDetails, {
                onSuccess: function (result) {
                    logMessage('Logged in!');
                    // switchToLoggedInView();

                    idToken = result.getIdToken().getJwtToken();
                    getCognitoIdentityCredentials();
                },

                onFailure: function(err) {
                    logMessage(err.message);
                    $("#loader").hide();
                },

            });
        }
    }

    /*
    Starting point for user registration flow with input validation
    */
    function register(){
        switchToRegisterView();

        if( !$('#userNameInput').val()  || !$('#passwordInput').val() || !$('#confirmPasswordInput').val() ) {
                logMessage('Please fill all the fields!');
        }else{
            if($('#passwordInput').val() == $('#confirmPasswordInput').val()){
                registerUser($('#userNameInput').val(), $('#passwordInput').val());
            }else{
                logMessage('Confirm password failed!');
            }
            
        }
    }

    /*
    Starting point for user verification using AWS Cognito with input validation
    */
    function verifyCode(){
        if( !$('#verificationCodeInput').val() ) {
            logMessage('Please enter verification field!');
        }else{
            $("#loader").show();
            cognitoUser.confirmRegistration($('#verificationCodeInput').val(), true, function(err, result) {
                if (err) {
                    logMessage(err.message);
                }else{
                    logMessage('Successfully verified code!');
                    // TODO: Add route to login page
                }
                
                $("#loader").hide();
            });
        }
    }

    /*
    User registration using AWS Cognito
    */
    function registerUser(username, password){
        var attributeList = [];
        
        var dataEmail = {
            Name : 'email',
            Value : username
        };

        var attributeEmail = new AmazonCognitoIdentity.CognitoUserAttribute(dataEmail);

        attributeList.push(attributeEmail);

        $("#loader").show();
        userPool.signUp(username, password, attributeList, null, function(err, result){
            if (err) {
                logMessage(err.message);
            }else{
                cognitoUser = result.user;
                logMessage('Registration Successful!');
                logMessage('Username is: ' + cognitoUser.getUsername());
                logMessage('Please enter the verification code sent to your Email.');
                switchToVerificationCodeView();
            }
            $("#loader").hide();
        });
    }

    /*
    This method will get temporary credentials for AWS using the IdentityPoolId and the Id Token received from AWS Cognito authentication provider.
    */
    function getCognitoIdentityCredentials(){
        AWS.config.region = region;

        var loginMap = {};
        loginMap['cognito-idp.' + region + '.amazonaws.com/' + userPoolId] = idToken;

        AWS.config.credentials = new AWS.CognitoIdentityCredentials({
            IdentityPoolId: identityPoolId,
            Logins: loginMap
        });

        AWS.config.credentials.clearCachedId();

        AWS.config.credentials.get(function(err) {
            if (err){
                logMessage(err.message);
            }
            else {
                // logMessage('AWS Access Key: '+ AWS.config.credentials.accessKeyId);
                // logMessage('AWS Secret Key: '+ AWS.config.credentials.secretAccessKey);
                // logMessage('AWS Session Token: '+ AWS.config.credentials.sessionToken);
            }

            $("#loader").hide();
        });
    }

    /*
    If user has logged in before, get the previous session so user doesn't need to log in again.
    */
    function getCurrentLoggedInSession(){

        $("#loader").show();
        userPool = new AmazonCognitoIdentity.CognitoUserPool(poolData);
        cognitoUser = userPool.getCurrentUser();

        if(cognitoUser != null){
            cognitoUser.getSession(function(err, session) {
                if (err) {
                    logMessage(err.message);
                }else{
                    logMessage('Session found! Logged in.');
                    // switchToLoggedInView();
                    idToken = session.getIdToken().getJwtToken();
                    getCognitoIdentityCredentials();
                }
                $("#loader").hide();
            });
        }else{
            // logMessage('Session expired. Please log in again.');
            // switchToRegisterView();
            $("#loader").hide();
        }

    }

    /*
    This is a logging method that will be used throught the application
    */
    function logMessage(message){
        $('#log').append(message + '</br>');
    }
</script>

@endsection