@extends('homepage::layout')

@push('styles')
    @vite([
        'app/Modules/Program_Accomplishments/assets/css/barangay-accomplishments.css',
    ])
@endpush

@push('scripts')
    @vite([
        'app/Modules/Program_Accomplishments/assets/js/barangay-accomplishments.js',
    ])
@endpush

