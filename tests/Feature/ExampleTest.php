<?php

test('returns a successful response', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
});

test('signup page returns a successful response', function () {
    $response = $this->get(route('signup'));

    $response->assertOk();
});
