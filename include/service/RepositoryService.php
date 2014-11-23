<?php
class RepositoryService extends ServiceBase {

  public function processRequest(WebRequest $request, WebResponse $response) {
    $action = $request->getParameter("action");
    switch ($action) {
      case "providers":
        return $this->processProviders($request, $response);
      case "list":
        return $this->processRepositoryList($request, $response);
      case "create":
        return $this->processCreate($request, $response);
      case "delete":
        return $this->processDelete($request, $response);
      case "browse":
        return $this->processBrowse($request, $response);
      case "info":
        return $this->processInfo($request, $response);
      case "paths":
        return $this->processPaths($request, $response);
      case "permissions":
        return $this->processPathPermissions($request, $response);
      case "addpath":
        return $this->processPathCreate($request, $response);
      case "deletepath":
        return $this->processPathDelete($request, $response);
    }
    return false;
  }

  public function processProviders(WebRequest $request, WebResponse $response) {
    $engine = SVNAdminEngine::getInstance();
    $providers = $engine->getKnownProviders(SVNAdminEngine::REPOSITORY_PROVIDER);
    $jsonProviders = array ();
    foreach ($providers as &$prov) {
      $jsonProv = new stdClass();
      $jsonProv->id = $prov->id;
      $jsonProv->editable = null;
      $jsonProviders[] = $jsonProv;
    }
    $response->done2json($jsonProviders);
    return true;
  }

  public function processRepositoryList(WebRequest $request, WebResponse $response) {
    $providerId = $request->getParameter("providerid");
    $offset = $request->getParameter("offset", 0);
    $num = $request->getParameter("num", 10);
    if (empty($providerId)) {
      return $this->processErrorMissingParameters($request, $response);
    }

    $engine = SVNAdminEngine::getInstance();
    $provider = $engine->getProvider(SVNAdminEngine::REPOSITORY_PROVIDER, $providerId);
    if (empty($provider)) {
      return $this->processErrorInvalidProvider($request, $response, $providerId);
    }

    $itemList = $provider->getRepositories($offset, $num);
    $repos = $itemList->getItems();

    $json = new stdClass();
    $json->editable = $provider->isEditable();
    $json->hasmore = $itemList->hasMore();
    $json->repositories = array ();
    foreach ($repos as &$repo) {
      $o = new stdClass();
      $o->id = $repo->getId();
      $o->name = $repo->getName();
      $o->displayname = $repo->getDisplayName();
      $json->repositories[] = $o;
    }
    $response->done2json($json);
    return true;
  }

  public function processCreate(WebRequest $request, WebResponse $response) {
    $providerId = $request->getParameter("providerid");
    $name = $request->getParameter("name");
    $options = $request->getParameter("options");
    if (empty($providerId) || empty($name)) {
      return $this->processErrorMissingParameters($request, $response);
    }

    $engine = SVNAdminEngine::getInstance();
    $provider = $engine->getProvider(SVNAdminEngine::REPOSITORY_PROVIDER, $providerId);
    if (empty($provider) || !$provider->isEditable()) {
      return $this->processErrorInvalidProvider($request, $response, $providerId);
    }

    $repo = $provider->create($name, $options);
    if (empty($repo)) {
      return $this->processErrorInternal($request, $response);
    }

    $json = new stdClass();
    $o = new stdClass();
    $o->id = $repo->getId();
    $o->name = $repo->getName();
    $o->displayname = $repo->getDisplayName();
    $json->repository = $o;
    $response->done2json($json);
    return true;
  }

  public function processDelete(WebRequest $request, WebResponse $response) {
    $providerId = $request->getParameter("providerid");
    $id = $request->getParameter("repositoryid");
    if (empty($providerId) || empty($id)) {
      return $this->processErrorMissingParameters($request, $response);
    }

    $engine = SVNAdminEngine::getInstance();
    $provider = $engine->getProvider(SVNAdminEngine::REPOSITORY_PROVIDER, $providerId);
    if (empty($provider) || !$provider->isEditable()) {
      return $this->processErrorInvalidProvider($request, $response, $providerId);
    }

    if (!$provider->delete($id)) {
      return $this->processErrorInternal($request, $response);
    }
    return true;
  }

  public function processBrowse(WebRequest $request, WebResponse $response) {
    return false;
  }

  public function processInfo(WebRequest $request, WebResponse $response) {
    $providerId = $request->getParameter("providerid");
    $repositoryId = $request->getParameter("repositoryid");
    if (empty($providerId) || empty($repositoryId)) {
      return $this->processErrorMissingParameters($request, $response);
    }

    $provider = SVNAdminEngine::getInstance()->getProvider(SVNAdminEngine::REPOSITORY_PROVIDER, $providerId);
    if (empty($provider)) {
      return $this->processErrorInvalidProvider($request, $response, $providerId);
    }

    $json = new stdClass();
    $json->entry = $provider->getInfo($repositoryId);
    $response->done2json($json);
    return true;
  }

  public function processPaths(WebRequest $request, WebResponse $response) {
    $providerId = $request->getParameter("providerid");
    $repositoryId = $request->getParameter("repositoryid");
    if (empty($providerId) || empty($repositoryId)) {
      return $this->processErrorMissingParameters($request, $response);
    }

    $engine = SVNAdminEngine::getInstance();
    $provider = $engine->getProvider(SVNAdminEngine::REPOSITORY_PROVIDER, $providerId);
    if (empty($provider)) {
      return $this->processErrorInvalidProvider($request, $response, $providerId);
    }

    $repository = $provider->findRepository($repositoryId);
    $authz = $provider->getSvnAuthz($repositoryId);
    $paths = $authz->getPaths($repository->getName());

    $json = new stdClass();
    $json->paths = array();
    foreach ($paths as &$path) {
      $obj = new stdClass();
      $obj->path = $path->path;
      $json->paths[] = $obj;
    }
    $response->done2json($json);
    return true;
  }

  public function processPathCreate(WebRequest $request, WebResponse $response) {
    $providerId = $request->getParameter("providerid");
    $repositoryId = $request->getParameter("repositoryid");
    $path = $request->getParameter("path");
    if (empty($providerId) || empty($repositoryId)) {
      return $this->processErrorMissingParameters($request, $response);
    }

    $provider = SVNAdminEngine::getInstance()->getProvider(SVNAdminEngine::REPOSITORY_PROVIDER, $providerId);
    if (empty($provider)) {
      return $this->processErrorInvalidProvider($request, $response, $providerId);
    }

    $repository = $provider->findRepository($repositoryId);
    $authz = $provider->getSvnAuthz($repositoryId);
    if (empty($repository) || empty($authz)) {
      return $this->processErrorInternal($request, $response);
    }

    $o = SvnAuthzFilePath::create($repository->getName(), $path);
    $authz->addPath($o);
    if (!SVNAdminEngine::getInstance()->commitSvnAuthzFile($authz)) {
      return $this->processErrorInternal($request, $response);
    }
    return true;
  }

  public function processPathDelete(WebRequest $request, WebResponse $response) {
    $providerId = $request->getParameter("providerid");
    $repositoryId = $request->getParameter("repositoryid");
    $path = $request->getParameter("path");
    if (empty($providerId) || empty($repositoryId)) {
      return $this->processErrorMissingParameters($request, $response);
    }

    $provider = SVNAdminEngine::getInstance()->getProvider(SVNAdminEngine::REPOSITORY_PROVIDER, $providerId);
    if (empty($provider)) {
      return $this->processErrorInvalidProvider($request, $response, $providerId);
    }

    $repository = $provider->findRepository($repositoryId);
    $authz = $provider->getSvnAuthz($repositoryId);
    if (empty($repository) || empty($authz)) {
      return true;
    }

    $o = new SvnAuthzFilePath();
    $o->repository = $repository->getName();
    $o->path = $path;
    $authz->removePath($o);
    if (!SVNAdminEngine::getInstance()->commitSvnAuthzFile($authz)) {
      return $this->processErrorInternal($request, $response);
    }
    return true;
  }

  public function processPathPermissions(WebRequest $request, WebResponse $response) {
    $providerId = $request->getParameter("providerid");
    $repositoryId = $request->getParameter("repositoryid");
    $path = $request->getParameter("path");
    if (empty($providerId) || empty($repositoryId)) {
      return $this->processErrorMissingParameters($request, $response);
    }

    $provider = SVNAdminEngine::getInstance()->getProvider(SVNAdminEngine::REPOSITORY_PROVIDER, $providerId);
    if (empty($provider)) {
      return $this->processErrorInvalidProvider($request, $response, $providerId);
    }

    $repository = $provider->findRepository($repositoryId);
    $authz = $provider->getSvnAuthz($repositoryId);
    $permissions = $authz->getPermissionsOfPath(SvnAuthzFilePath::create($repository->getName(), $path));

    $json = new stdClass();
    $json->permissions = array();
    foreach ($permissions as &$permission) {
      $jsonPerm = new stdClass();
      $jsonPerm->member = new stdClass();
      $jsonPerm->member->id = $permission->member->asMemberString();
      $jsonPerm->member->displayname = $permission->member->asMemberString();
      $jsonPerm->member->type = "";
      $jsonPerm->permission = $permission->permission;
      $json->permissions[] = $jsonPerm;
    }
    $response->done2json($json);
    return true;
  }

}