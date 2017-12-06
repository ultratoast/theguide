import React, { Component } from 'react';
import { Provider } from 'react-redux';
import { BrowserRouter as Router, Route } from 'react-router-dom';

import Index from './views/Index';
import Project from './views/Project';
import CreateProject from './views/CreateProject';
import Module from '.views/Module';
import CreateModule from '.views/CreateModule';
import Tag from './views/Tag';

class App extends Component {
  render() {
    const { store } = this.props;
    return (
      <Provider store={store}>
        <Router>
          <Route path="/" component={<Index {...props} />} />
          <Route path="/projects/:projectid" component={<Project {...props} />} />
          <Route path="/projects/create" component={<CreateProject {...props} />} />
          <Route path="/modules/:moduleid" component={<Module {...props} />} />
          <Route path="/modules/create" component={<CreateModule {...props} />} />
          <Route path="/tag/:tagid" component={<Tag {...props} />} />
        </Router>
      </Provider>
    );
  }
}

export default App;
