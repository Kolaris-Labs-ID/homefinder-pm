<?php

test('example test returns successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
