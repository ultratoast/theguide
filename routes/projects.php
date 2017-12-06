<?php

$app->get('/list-projects',  function(Request $request, Response $response) {
	//generic search function to list projects. should be filterable by personnel, location, longevity, open housing available
	$query = $this->get('db')->prepare('SELECT title,summary,location,date_started, date_ended, ,score, FROM projects WHERE active = TRUE');
	try {
		$this->db->beginTransaction();
		$query->execute();
		$result = $query->fetchAll();
		$this->db->commit();
	} catch (PDOException $error) {
		$this->db->rollback();
		$result = '{"Error":'.$error->getMessage().'}';
		$this->logger->addInfo("Error! ".$error->getMessage());
	}
	return $result;
});

$app->get('/project/{project_id}',  function(Request $request, Response $response) {
	//get a specific project
	$project_id = $request->getArgument('project_id');
	$query = $this->get('db')->prepare('SELECT title, summary,location, score, date_started,date_ended,food_provided,shelter_provided, beds FROM projects, order, title, summary, location, score, date_created FROM modules, msid, title, summary, order, participants, active FROM module_steps, date_created, comment, score, pid, mid, msid FROM comments, filename FROM images, module_types.title AS module_types FROM modules_to_types INNER JOIN module_steps ON module_steps.mid = modules.mid LEFT OUTER JOIN comments ON comments.mid = modules.mid LEFT OUTER JOIN comments ON comments.pid = projects.pid LEFT OUTER JOIN comments ON comments.msid = module_steps.msid LEFT OUTER JOIN images ON images.mid = modules.mid LEFT OUTER JOIN images ON images.pid = projects.pid LEFT OUTER JOIN images ON images.msid = module_steps.msid INNER JOIN modules ON module_to_types.mid = modules.mid INNER JOIN module_types ON modules_to_types.mtid = module_types.mtid INNER JOIN projects ON projects_to_modules.pid = projects.pid INNER JOIN modules on projects_to_modules.mid = modules.mid WHERE projects.pid = :project_id');
	try {
		$this->db->beginTransaction();
		$query->execute(array('project_id'=>$project_id));
		$result = $query->fetchAll();
		$this->db->commit();
	} catch (PDOException $error) {
		$this->db->rollback();
		$result = '{"Error":'.$error->getMessage().'}';
		$this->logger->addInfo("Error! ".$error->getMessage());
	}
	return $result; 
});

$app->post('/create-project', function(Request $request, Response $response) {
	//create a new project with an orgaanizer's email
	$data = json_decode($request->getParsedBody(),true);
	$files =  $request->getUploadedFiles();
	$directory = $this->get('upload_directory');
	$project = array();
	$project['pid'] = false;
	$project['title'] = $data->title;
	$project['creator'] = $data->email;
	$project['summary'] = $data->summary;
	$project['location'] = $data->location;
	$project['score'] = 0;
	$project['date_started'] = $data->date_started;
	$project['date_ended'] = $date->date_ended;
	$project['shelter_provided'] = $data->shelter_provided;
	$project['personnel'] = 0;
	$project['food_provided'] = $data->food_provided;
	$project['beds'] = $data->beds;
	$token = bin2hex(random_bytes(16));
	$project['uuid'] = $token;
	$project['modules'] = array();
	$project['images'] = array();
	$project['active'] = false;
	//save project to database
	$query = $this->get('db')->preapre('INSERT INTO projects SET title = :title, summary = :summary, location = :location, score = :score, date_started = :date_started, date_ended = :date_ended, creator = :creator, personnel = :personnel, food_provided = :food_provided, shelter_provided = :shelter_provided, beds = :beds, active = :active');
	try {
		$this->db->beginTransaction();
		$query->execute(array(
			'uuid'=>$project['uuid'],
			'title' => $project['title'],
			'score' => $project['score'],
			'creator' => $project['creator'],
			'summary' => $project['summary'],
			'location' => $project['location'],
			'personnel' => $project['personnel'],
			'date_started' => $project['date_started'],
			'date_ended' => $project['date_ended'],
			'food_provided' => $project['food_provided'],
			'shelter_provided' => $project['shelter_provided'],
			'beds' => $project['beds'],
			'active' => $project['active'],
		));
		$project['pid'] = $this->db->lastInsertId();
		$this->db->commit();
	} catch (PDOException $error) {
		$this->db->rollback();
		$this->logger->addInfo("Error Saving Project! ".$error->getMessage());
	}
	if ($project['pid']) {
		//loop through each module, creating a new entry in the projects_to_modules table as we go
		foreach ($data->modules as $m) {
			$mid = $m->mid;
			$query = $this->get('db')->prepare('INSERT INTO projects_to_modules SET pid = :pid, mid = :mid');
			try {
				$this->db->beginTransaction();
				$query->execute(array(
					'pid'=>$project['pid'],
					'mid' => $mid,
				));
				$this->db->commit();
			} catch (PDOException $error) {
				$this->db->rollback();
				$this->logger->addInfo("Error Storing Module  Reference to Project!! ".$error->getMessage());
			}		
		}
	}
	//loop through main module images and save them
	foreach ($files['project_images'] as $pi) {
		if ($ti->getError() === UPLOAD_ERR_OK) {
            $filename = moveUploadedFile($directory, $pi);
            $query = $this->get('db')->preapre('INSERT INTO images SET filename = :filename');
			try {
				$this->db->beginTransaction();
				$query->execute(array(
					'filename' => $image['filename'],
					'pid' => $project['pid']
				));
				$this->db->commit();
			} catch (PDOException $error) {
				$this->db->rollback();
				$this->logger->addInfo("Error Saving Image! ".$error->getMessage());
			}	
        }
	}
	//send email with master link
	$mail = new PHPMailer(true);
	try {
	    //Server settings
	    $mail->SMTPDebug = 2;                                 // Enable verbose debug output
	    $mail->isSMTP();                                      // Set mailer to use SMTP
	    $mail->Host = 'smtp1.theguide.metaworks.co;smtp2.theguide.metaworks.co';  // Specify main and backup SMTP servers
	    $mail->SMTPAuth = true;                               // Enable SMTP authentication
	    $mail->Username = 'feedback@theguide.metaworks.co';                 // SMTP username
	    $mail->Password = 'secret';                           // SMTP password
	    $mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
	    $mail->Port = 587;                                    // TCP port to connect to

	    //Recipients
	    $mail->setFrom('feedback@theguide.metaworks.co', 'Mailer');
	    $mail->addAddress($module['creator'], 'Guide Contibutor'); 
	    $mail->addBCC('master@theguide.metaworks.co');

	    //Content
	    $mail->isHTML(true);                                  // Set email format to HTML
	    $mail->Subject = 'DO NOT DELETE THIS EMAIL!!! Thank you for creating a new project on The Guide.';
	    $mail->Body    = '<h1>Thank You!</h1><p>Please confirm your project by clicking the link to Publish below.</p><p>Your valuable contribution has helped make the guide a more powerful platform. Please keep this email in your archives.</p><p>To edit or delete your project click one of the links below. No-one else has this email, but keep in mind anyone with access to this email basically controls your new project!</p><ul><li><a target="_blank" href="http://'.$_SERVER['HOST_NAME'].'/confirm/create-project/'.$project['pid'].'/'.$project['uuid'].'">Publish project</a></li><li><a target="_blank" href="http://'.$_SERVER['HOST_NAME'].'/confirm/update-project/'.$project['pid'].'/'.$project['uuid'].'">Edit project</a></li><li><a target="_blank" href="http://'.$_SERVER['HOST_NAME'].'/confirm/delete-project/'.$project['pid'].'/'.$project['uuid'].'">Delete project</a></li></ul>';
	    $mail->AltBody = 'Thank you for publishing on the guide! Please copy and paste the following link into your browser to publish: &#xa; http://'.$_SERVER['HOST_NAME'].'/confirm/create-project/'.$project['pid'].'/'.$project['uuid'].'&#xa; To edit: http://'.$_SERVER['HOST_NAME'].'/confirm/update-project/'.$project['pid'].'/'.$project['uuid'].'&#xa To delete: http://'.$_SERVER['HOST_NAME'].'/confirm/delete-project/'.$project['pid'].'/'.$project['uuid'];

	    $mail->send();
	    $this->logger->addMessage('Message has been sent';
	} catch (Exception $e) {
	    $this->logger->addMessage('Mailer Error: ' . $mail->ErrorInfo);
	}
	$response->write('Project Saved');
});

$app->post('/update-project/{project_id}/{email_key}',  function(Request $request, Response $response) {
	//im not even sure what you would change, modules used? timeline? seems scary to me. only accessible via email
	$pid = $request->getArgument('project_id') || false;
	$uuid = $request->getArgument('email_key') || false;
	//this is a dumb way to do this very important check. 
	//this should not continue if the uuid isn't the right thing
	if (!pid || !uuid) {
		$this->logger->addMessage('Bad Request, Missing IDs');
		$response->write('Bad Request, Missing IDs');
	} else {
		$data = json_decode($request->getParsedBody(),true);
		$files =  $request->getUploadedFiles();
		$directory = $this->get('upload_directory');
		$project = array();
		$project['title'] = $data->title;
		$project['summary'] = $data->summary;
		$project['location'] = $data->location;
		$project['shelter_provided'] = $data->shelter_provided;
		$project['date_started'] = $data->date_started;
		$project['date_ended'] = $date->date_ended;
		$project['food_provided'] = $data->food_provided;
		$project['beds'] = $data->beds;
		$project['modules'] = array();
		$project['images'] = array();
		//save project to database
		$query = $this->get('db')->preapre('
		UPDATE projects SET title = :title, summary = :summary, location = :location, food_provided = :food_provided, shelter_provided = :shelter_provided, beds = :beds, date_started = :date_started, date_ended = :date_ended');
		try {
			$this->db->beginTransaction();
			$query->execute(array(
				'title' => $project['title'],
				'summary' => $project['summary'],
				'location' => $project['location'],
				'food_provided' => $project['food_provided'],
				'shelter_provided' => $project['shelter_provided'],
				'beds' => $project['beds']
			));
			$this->db->commit();
		} catch (PDOException $error) {
			$this->db->rollback();
			$this->logger->addInfo("Error Saving Project! ".$error->getMessage());
		}
		//first we drop all the old entries for modules in the db
		$query = $this->get('db')->preapre('DELETE FROM projects_to_modules WHERE pid = :pid');
		try {
			$this->db->beginTransaction();
			$query->execute(array(
				'pid'=>$pid,
			));
			$this->db->commit();
		} catch (PDOException $error) {
			$this->db->rollback();
			$this->logger->addInfo("Error Removing Old MProjects to Modules entries! ".$error->getMessage());
		}
		//loop through each module, creating a new entry in the projects_to_modules table as we go
		foreach ($data->modules as $m) {
			$mid = $m->mid;	
			$query = $this->get('db')->prepare('INSERT INTO projects_to_modules SET pid = :pid, mid = :mid');
			try {
				$this->db->beginTransaction();
				$query->execute(array(
					'pid'=>$pid,
					'mid' => $mid,
				));
				$this->db->commit();
			} catch (PDOException $error) {
				$this->db->rollback();
				$this->logger->addInfo("Error Storing Module  Reference to Project!! ".$error->getMessage());
			}		
		}
		//loop through main module images and save them
		foreach ($files['project_images'] as $pi) {
			if ($mi->getError() === UPLOAD_ERR_OK) {
	            $filename = moveUploadedFile($directory, $pi);
	            $query = $this->get('db')->preapre('INSERT INTO images SET filename = :filename, pid = :pid');
				try {
					$this->db->beginTransaction();
					$query->execute(array(
						'filename' => $image['filename'],
						'pid' => $pid
					));
					$this->db->commit();
				} catch (PDOException $error) {
					$this->db->rollback();
					$this->logger->addInfo("Error Saving Image! ".$error->getMessage());
				}	
      }
		}
	}
	$response->write('Project Updated!');
});

$app->post('/edit-project-schedule/{project_id}',  function(Request $request, Response $response) {
	//generates an email to the project organizer and adds or removes someone from the schedule for a specific task/number of days. new participants will be assigned an id and a rating of 0. if someone is removed from the schedule, they are listed in "past participants" along with their score
	//idk about this. it should definitely be possible to do this, but how? is ghere a project schedule in addition to the proejct itself? could modules track progress based on data they have about how long it takes to complete the module?
	//how do we track time consumed in the app?
});

$app->post('/delete-project/{project_id}/{email_key}',  function(Request $request, Response $response) {
	//delete a project, requires email link (craigslist)
	$pid = $request->getArgument('project_id') || false;
	$uuid = $request->getArgument('email_key') || false;
	//this is a dumb way to do this very important check. 
	//this should not continue if the uuid isn't the right thing
	if (!pid || !uuid) {
		$this->logger->addMessage('Bad Request, Missing IDs');
		$response->write('Bad Request, Missing IDs');
	} else {
		$query = $this->db->prepare('UPDATE projects SET active = FALSE WHERE pid = :pid AND uuid = :uuid');
		try {
			$this->db->beginTransaction();
			$query->execute(array(
				'uuid' => $uuid,
				'pid' => $pid
			));
			$this->db->commit();
			$response->write('Project Ended. Use the publish link in your master email to make it active again.');
		} catch (PDOException $error) {
			$this->db->rollback();
			$this->logger->addInfo("Error Ending Project! ".$error->getMessage());
			$response->write('Error ending project, please try again');
		}		
	}
});

$app->group('/confirm', function(Request $request, Response $response) {
	//landing pages for email confirmation, do database stuff

	$app->get('/create-project/{project_id}/{email_key}', function(Request $request, Response $response) {
		$pid = $request->getArgument('project_id') || false;
		$uuid = $request->getArgument('email_key') || false;
		//still a really du,b way to make this check
		if (!$pid || !$uuid) {
			$date_activated = new Date('NOW');
			$query = $this->db->prepare('UPDATE projects SET active = TRUE, date_activated = :date_activated WHERE pid = :pid AND uuid = :uuid');
			try {
				$this->db->beginTransaction();
				$query->execute(array(
					'uuid' => $uuid,
					'pid' => $pid,
					'date_activated' => $date_activated
				));
				$this->db->commit();
				$response->write('Project Active! Use the delete link in your master email to remove it from The Guide.');
			} catch (PDOException $error) {
				$this->db->rollback();
				$this->logger->addInfo("Error activating project! ".$error->getMessage());
				$response->write('Error activating project, please try again');
			}		
		}	
	});
	$app->get('/update-project/{project_id}/{email_key}', function(Request $request, Response $response) {

	});
	$app->get('/delete-project/{project_id}/{email_key}', function(Request $request, Response $response) {

	});
});