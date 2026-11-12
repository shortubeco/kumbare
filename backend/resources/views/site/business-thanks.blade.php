@extends('layouts.site')

@section('content')
<section class="section">
    <div class="wrap metin">
        <h1>Başvurunuz alındı</h1>
        <div class="basarili">
            <p>Başvurunuzu inceleyip sonucu e-posta ile bildireceğiz. Onaylandığında başvuruda yazdığınız e-posta ve şifreyle işletme paneline giriş yapabilirsiniz.</p>
        </div>
        <p><a class="btn btn-ghost" href="{{ route('web.home') }}">Ana sayfaya dön</a></p>
    </div>
</section>
@endsection
