<?php

$app->get('/tag/{tag_id}',  function(Request $request, Response $response) {
	//load a single tag by id
	$tid = $request->getArgument('tag_id');
	$query = $this->get('db')->prepare('SELECT title,summary,author, date_created, date_modified, score, FROM tags, date_created, comment, score, tid FROM comments, filename FROM images, LEFT OUTER JOIN comments ON comments.tid = :tid, LEFT OUTER JOIN images ON images.tid = :tid WHERE tid = :tid');
	try {
		$this->db->beginTransaction();
		$query->execute(array('tid' => $tid));
		$result = $query->fetchAll();
		$this->db->commit();
	} catch (PDOException $error) {
		$this->db->rollback();
		$result = '{"Error Fetching Tag":'.$error->getMessage().'}';
		$this->logger->addInfo("Error Fetching Tag! ".$error->getMessage());
	}
	return $result;
});

$app->post('/tags/quick-search',  function(Request $request, Response $response) {
	//load an array of tags by id
	$data = json_decode($request->getParsedBody(),true);
	$query = $this->get('db')->prepare('SELECT title, tid FROM tags WHERE match(title) against :search_term');
	try {
		$this->db->beginTransaction();
		$query->execute(array('search_term' => $data->tag_name));
		$result = $query->fetchAll();
		$this->db->commit();
	} catch (PDOException $error) {
		$this->db->rollback();
		$result = '{"Error Fetching Tag":'.$error->getMessage().'}';
		$this->logger->addInfo("Error Fetching Tag! ".$error->getMessage());
	}
	return $result;
});

$app->post('/create-tag',  function(Request $request, Response $response) {
	//save new tag
	$data = json_decode($request->getParsedBody(),true);
	$tag = array();
	$tag['tid'] = false;
	$tag['title'] = $data->title;
	$tag['summary'] = $data->summary;
	$tag['author'] = $data->email;
	$tag['score'] = 0;
	$tag['date_created'] = $data->date_created;
	$token = bin2hex(random_bytes(16));
	$tag['uuid'] = $token;
	$tag['active'] = false;
	$tag['images'] = array();
	//save tag to database
	$query = $this->get('db')->preapre('INSERT INTO tags SET title = :title, summary = :summary, location = :location, score = :score, date_created = :date_created, date_modified = :date_modified, author = :author, personnel = :personnel, food_provided = :food_provided, shelter_provided = :shelter_provided, beds = :beds');
	try {
		$this->db->beginTransaction();
		$query->execute(array(
			'uuid'=>$tag['uuid'],
			'title' => $tag['title'],
			'score' => $tag['score'],
			'author' => $tag['author'],
			'summary' => $tag['summary'],
			'date_created' => $tag['date_created'],
			'date_modified' => $tag['date_modified'],
			'active' => $tag['active'],
		));
		$tag['tid'] = $this->db->lastInsertId();
		$this->db->commit();
	} catch (PDOException $error) {
		$this->db->rollback();
		$this->logger->addInfo("Error Saving Tag! ".$error->getMessage());
	}
	if ($tag['tid']) {
		//loop through main module images and save them
		foreach ($files['tag_images'] as $ti) {
			if ($ti->getError() === UPLOAD_ERR_OK) {
	            $filename = moveUploadedFile($directory, $ti);
	            $query = $this->get('db')->preapre('INSERT INTO images SET filename = :filename');
				try {
					$this->db->beginTransaction();
					$query->execute(array(
						'filename' => $image['filename'],
						'tid' => $tag['tid']
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
		    $mail->Subject = 'DO NOT DELETE THIS EMAIL!!! Thank you for creating a new tag article on The Guide.';
		    $mail->Body    = '<h1>Thank You!</h1><p>Please confirm your tag article by clicking the link to Publish below.</p><p>Your valuable contribution has helped make the guide a more powerful platform. Please keep this email in your archives.</p><p>To edit or delete your tag click one of the links below. No-one else has this email, but keep in mind anyone with access to this email basically controls your new tag!</p><ul><li><a target="_blank" href="http://'.$_SERVER['HOST_NAME'].'/confirm/create-tag/'.$tag['tid'].'/'.$tag['uuid'].'">Publish tag</a></li><li><a target="_blank" href="http://'.$_SERVER['HOST_NAME'].'/confirm/update-tag/'.$tag['tid'].'/'.$tag['uuid'].'">Edit tag</a></li><li><a target="_blank" href="http://'.$_SERVER['HOST_NAME'].'/confirm/delete-tag/'.$tag['tid'].'/'.$tag['uuid'].'">Delete tag</a></li></ul>';
		    $mail->AltBody = 'Thank you for publishing on the guide! Please copy and paste the following link into your browser to publish: &#xa; http://'.$_SERVER['HOST_NAME'].'/confirm/create-tag/'.$tag['tid'].'/'.$tag['uuid'].'&#xa; To edit: http://'.$_SERVER['HOST_NAME'].'/confirm/update-tag/'.$tag['tid'].'/'.$tag['uuid'].'&#xa To delete: http://'.$_SERVER['HOST_NAME'].'/confirm/delete-tag/'.$tag['tid'].'/'.$tag['uuid'];

		    $mail->send();
		    $this->logger->addMessage('Message has been sent';
		} catch (Exception $e) {
		    $this->logger->addMessage('Mailer Error: ' . $mail->ErrorInfo);
		}
		$response->write('Tag Saved');
	}
});

$app->post('/edit-tag/{tag_id}',  function(Request $request, Response $response) {
	$tid = $request->getArgument('tag_id') || false;
	$uuid = $request->getArgument('email_key') || false;
	//this is a dumb way to do this very important check. 
	//this should not continue if the uuid isn't the right thing
	if (!tid || !uuid) {
		$this->logger->addMessage('Bad Request, Missing IDs');
		$response->write('Bad Request, Missing IDs');
	} else {
		$data = json_decode($request->getParsedBody(),true);
		$files =  $request->getUploadedFiles();
		$directory = $this->get('upload_directory');
		$tag = array();
		$tag['title'] = $data->title;
		$tag['summary'] = $data->summary;
		$tag['date_modified'] = $date->date_modified;
		$tag['images'] = array();
		//save tag to database
		$query = $this->get('db')->preapre('
		UPDATE tags SET title = :title, summary = :summary, date_modified = :date_modified');
		try {
			$this->db->beginTransaction();
			$query->execute(array(
				'title' => $tag['title'],
				'summary' => $tag['summary'],
				'date_modified' => $tag['date_modified'],
			));
			$this->db->commit();
		} catch (PDOException $error) {
			$this->db->rollback();
			$this->logger->addInfo("Error Saving Tag! ".$error->getMessage());
		}
		//loop through main module images and save them
		foreach ($files['tag_images'] as $pi) {
			if ($mi->getError() === UPLOAD_ERR_OK) {
	            $filename = moveUploadedFile($directory, $pi);
	            $query = $this->get('db')->preapre('INSERT INTO images SET filename = :filename, tid = :tid');
				try {
					$this->db->beginTransaction();
					$query->execute(array(
						'filename' => $image['filename'],
						'tid' => $tid
					));
					$this->db->commit();
				} catch (PDOException $error) {
					$this->db->rollback();
					$this->logger->addInfo("Error Saving Image! ".$error->getMessage());
				}	
      }
		}
		$response->write('Tag Updated!');
	}
});


$app->post('/delete-tag/{tag_id}/{email_key}',  function(Request $request, Response $response) {
	//delete a tag, requires email link (craigslist)
	$tag = $request->getArgument('tag_id') || false;
	$uuid = $request->getArgument('email_key') || false;
	//this is a dumb way to do this very important check. 
	//this should not continue if the uuid isn't the right thing
	if (!tag || !uuid) {
		$this->logger->addMessage('Bad Request, Missing IDs');
		$response->write('Bad Request, Missing IDs');
	} else {
		$query = $this->db->prepare('UPDATE tags SET active = FALSE WHERE tag = :tag AND uuid = :uuid');
		try {
			$this->db->beginTransaction();
			$query->execute(array(
				'uuid' => $uuid,
				'tag' => $tag
			));
			$this->db->commit();
			$response->write('Tag unpublished. Use the publish link in your master email to make it active again.');
		} catch (PDOException $error) {
			$this->db->rollback();
			$this->logger->addInfo("Error unpublishing tag! ".$error->getMessage());
			$response->write('Error unpublishing tag, please try again');
		}		
	}
});


$app->group('/confirm', function(Request $request, Response $response) {
	$this->get('/create-tag/{tag_id}/{email_key', function(Request $request, Response $response) {
		$tid = $request->getArgument('tag_id') || false;
		$uuid = $request->getArgument('email_key') || false;
		//still a really du,b way to make this check
		if (!$tid || !$uuid) {
			$date_activated = new Date('NOW');
			$query = $this->db->prepare('UPDATE tags SET active = TRUE, date_activated = :date_activated WHERE tid = :tid AND uuid = :uuid');
			try {
				$this->db->beginTransaction();
				$query->execute(array(
					'uuid' => $uuid,
					'tid' => $tid,
					'date_activated' => $date_activated
				));
				$this->db->commit();
				$response->write('Tag active! Use the delete link in your master email to remove it from The Guide.');
			} catch (PDOException $error) {
				$this->db->rollback();
				$this->logger->addInfo("Error activating tag! ".$error->getMessage());
				$response->write('Error activating tag, please try again');
			}		
		}
	})
	//landing page for email confirmation, do database stuff
	$this->get('/edit-tag/{tag_id}/{email_key}', function(Request $request, Response $response) {
	
	});

		$this->get('/delete-tag/{tag_id}/{email_key}', function(Request $request, Response $response) {
	
	});
});