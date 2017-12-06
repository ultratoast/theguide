<?php

$app->group('/rate', function(Request $request, Response $response) {
	//up or downvote. scores are permanent, requires email confirmation, only one vote per email
	$this->post('/module/{module_id}', function(Request $request, Response $response) {
		//module may have additional data about which step the rating applies to
	});
	$this->post('/project/{project_id}',  function(Request $request, Response $response) {
		
	});
	$this->post('/participant/{participant_id}',  function(Request $request, Response $response) {

	});
	$this->post('/tag-edit/{tag_id}',  function(Request $request, Response $response) {
	});
});

$app->group('/confirm-vote',  function(Request $request, Response $response) {
	//landing pages for email confirmation, do database stuff
	$this->get('/module/{module_id}/{email_key}', function(Request $request, Response $response) {

	});

	$this->get('/project/{project_id}/{email_key}',  function(Request $request, Response $response) {
		
	});

	$this->get('/participant/{participant_id}/{email_key}',  function(Request $request, Response $response) {
	
	});

	$this->get('/tag/{tag_id}',  function(Request $request, Response $response) {
		//if the score is more than 100, save the edited tag as the new tag and reset content. if the score is -10 or less, delete the edit
	});
});

$app->group('/comments', function(Request $request, Response $response) {
	//load all comments associated with modules/projects
	$this->get('/module/{module_id}', function(Request $request, Response $response) {
		//module comments may have additional data about which step of the module the comment applies to
	});

	$this->get('/project/{project_id}', function(Request $request, Response $response) {
		
	});
});