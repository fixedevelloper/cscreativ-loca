<!DOCTYPE html>
<html lang="fr" class="js">

<head>
    <meta charset="utf-8">
    <meta name="author" content="Rodrigue mbah">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Fav Icon  -->
    <link rel="shortcut icon" href="{!! asset('favicon.png') !!}">
    <!-- Page Title  -->
    <title>@yield('title') | {!! config('app.name') !!}</title>
    <!-- StyleSheets  -->
    <link rel="stylesheet" href="{!! asset('assets/css/dashlite.min.css') !!}?ver=3.2.3">
    <link id="skin-default" rel="stylesheet" href="{!! asset('assets/css/theme.css') !!}?ver=3.2.3">
    <style type="text/css">
        .fl-wrapper {
            position: fixed;
            -webkit-transition: all 1s ease-in-out;
            -moz-transition: all 1s ease-in-out;
            transition: all 1s ease-in-out;
            width: 24em;
            z-index: 9999 !important;
        }
        @media (min-width: 768px) {
            .profile-ud-item {
                width: 100% !important;
                padding: 0 3.25rem;
            }
        }/* */
    </style>
</head>

<body class="nk-body bg-white has-sidebar ">
<div class="nk-app-root">
    <!-- main @s -->
    <div class="nk-main ">
        @include('_partials._siderbase')
        <div class="nk-wrap ">
            <!-- main header @s -->
        @include('_partials._header')
        <!-- main header @e -->
            <!-- content @s -->
            <div class="nk-content nk-content-fluid">
                <div class="container-xl wide-lg">
                    <div class="nk-content-body">
                            @yield('content')
                        </div>
                    </div>
            </div>
            @include('_partials._footer')
        </div>
        <!-- main @e -->
    </div>
</div>
<!-- JavaScript -->
<script src="{!! asset('assets/js/bundle.js') !!}"></script>
<script src="{!! asset('assets/js/scripts.js') !!}"></script>
<script>
    var configs={
        routes:{
            index: "{{\Illuminate\Support\Facades\URL::to('/')}}",

        }
    }
</script>
@livewireStyles
@livewireScripts
@stack("js")
</body>

</html>
