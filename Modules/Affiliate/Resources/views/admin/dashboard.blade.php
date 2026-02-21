@extends('backend.layouts.app')

@section('title', 'Affiliate Admin Dashboard')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card card-custom text-center">
                <div class="card-body">
                    <h5>Total Affiliates</h5>
                    <h3>{{ $totalAffiliates }}</h3>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card card-custom text-center">
                <div class="card-body">
                    <h5>Total Visitors</h5>
                    <h3>{{ $totalVisitors }}</h3>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card card-custom text-center">
                <div class="card-body">
                    <h5>Total Conversions</h5>
                    <h3>{{ $totalConversions }}</h3>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card card-custom text-center">
                <div class="card-body">
                    <h5>Total Earnings</h5>
                    <h3>${{ number_format($totalEarnings,2) }}</h3>
                </div>
            </div>
        </div>
    </div>

    {{-- Top Affiliates Table --}}
    <div class="row mt-4">
        <div class="col-12">
            <div class="card card-custom">
                <div class="card-header">
                    <h5>Top Affiliates by Conversions</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Ref Code</th>
                                <th>Conversions</th>
                                <th>Wallet Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($topAffiliates as $affiliate)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $affiliate->user->name }}</td>
                                    <td>{{ $affiliate->user->email }}</td>
                                    <td>{{ $affiliate->ref_code }}</td>
                                    <td>{{ $affiliate->conversions_count }}</td>
                                    <td>${{ number_format($affiliate->wallet_total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
