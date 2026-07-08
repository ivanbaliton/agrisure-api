<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>AgriSure OTP</title>

<style>

body{
    margin:0;
    padding:0;
    background:#eef3ef;
    font-family:Arial, Helvetica, sans-serif;
}

.wrapper{
    width:100%;
    background:#eef3ef;
    padding:40px 0;
}

.container{
    width:620px;
    max-width:95%;
    margin:auto;
    background:#ffffff;
    border-radius:18px;
    overflow:hidden;
    box-shadow:0 12px 40px rgba(0,0,0,.08);
}

/* Header */

.header{
    background:linear-gradient(135deg,#2E7D32,#43A047);
    color:white;
    text-align:center;
    padding:45px 35px;
}

.logo{
    font-size:52px;
    line-height:1;
}

.header h1{
    margin:15px 0 8px;
    font-size:34px;
}

.header p{
    margin:0;
    opacity:.9;
    font-size:15px;
}

/* Body */

.content{
    padding:45px;
    color:#333;
}

.content h2{
    margin-top:0;
    color:#1b5e20;
}

.content p{
    font-size:16px;
    line-height:1.8;
}

/* OTP */

.otp-box{
    margin:35px auto;
    width:260px;
    text-align:center;
    background:#F1F8E9;
    border:2px dashed #43A047;
    border-radius:14px;
    padding:22px;
}

.otp{
    font-size:42px;
    letter-spacing:12px;
    font-weight:bold;
    color:#1B5E20;
}

/* Notice */

.notice{
    background:#FFF8E1;
    border-left:5px solid #FFC107;
    padding:18px;
    border-radius:8px;
    margin-top:30px;
}

.notice strong{
    color:#E65100;
}

.security{
    background:#F5F5F5;
    padding:18px;
    border-radius:8px;
    margin-top:20px;
    font-size:14px;
    color:#555;
}

/* Footer */

.footer{
    background:#fafafa;
    text-align:center;
    padding:30px;
    border-top:1px solid #eeeeee;
}

.footer h3{
    color:#2E7D32;
    margin:0 0 10px;
}

.footer p{
    margin:4px;
    color:#777;
    font-size:13px;
}

.social{
    margin-top:18px;
    font-size:22px;
}

</style>
</head>

<body>

<div class="wrapper">

<div class="container">

<div class="header">

<div class="logo">🌾</div>

<h1>AgriSure</h1>

<p>
Digital Crop Insurance & Agricultural Assistance System
</p>

</div>

<div class="content">

<h2>Hello, {{ $name }}!</h2>

<p>
We received a request to sign in to your
<strong>AgriSure</strong> account.
To continue securely, please use the verification code below.
</p>

<div class="otp-box">

<div class="otp">

{{ $otp }}

</div>

</div>

<div class="notice">

<strong>OTP Expiration</strong><br><br>

This verification code will expire in
<strong>3 minutes</strong>.

</div>

<div class="security">

<b>Security Reminder</b>

<ul>
<li>Never share this code with anyone.</li>
<li>AgriSure staff will never ask for your OTP.</li>
<li>If you did not request this login, you may safely ignore this email.</li>
</ul>

</div>

<p style="margin-top:35px;">

Thank you for using <strong>AgriSure</strong> in supporting farmers through secure digital agricultural services.

</p>

</div>

<div class="footer">

<h3>AgriSure</h3>

<p>
Municipality of San Agustin, Isabela
</p>

<p>
Digital Crop Insurance & Agricultural Assistance Management System
</p>

<div class="social">
🌾 🚜 🌱
</div>

<p style="margin-top:18px;">
© {{ date('Y') }} AgriSure. All Rights Reserved.
</p>

</div>

</div>

</div>

</body>
</html>