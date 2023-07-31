<!--@extends('layouts.app')-->

@section('content')

    <p style="color:black;">Vous venez de créer votre compte DRAT. </p><br>
    <a href="https://draht.team-solutions.net/#/verify/{{$ref_user}}" style="background-color: #4CAF50; border: none;color: white;
  padding: 15px 32px;  text-align: center;  text-decoration: none;  display: inline-block; font-size: 16px;">Cliquez ici pour activer votre compte</a>
    <p>Cordialement.</p> <br>
    <p style="color:black; font-weight:bold; size:14px;">L'Association Team@Solutions.</p>
@endsection
