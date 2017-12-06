import React, { Component } from 'react';
import { connect } from 'react-redux';

import './styles.index.css';

class Index extends Component {
  state = {
    calmed: false,
    searchModules: this.props.moduleSearchResults || [],
    searchProjects: this.props.projectSearchResults || [],
    searchTerm: '',
  }
  componentDidMount() {
    setTimeout(this.setState({calmed: true}), 3000);
  }
  submitSearch = (event) => {
    const { guideSearch } = this.props;
    if (event.keyCode === 13) {
      event.preventDefault();
      console.log(event.target.value);
      this.setState({searchTerm: event.target.value}, () => guideSearch(this.state.searchTerm));
    }
  }
  render() {
    const { searchModules, searchProjects } = this.state;
    const moduleSearchResults = (searchModules.length > 0 ? (
          <div className="module-results">
            <div className="subheader">Do these modules match your search?</div>
            <ul className="results-list">
              {
                searchModules.map(module => (
                  <li key={module.mid}>
                    <Link to={`/modules/${module.mid}`}>
                      <div className="result-title">{module.title}</div>
                      <div className="result-image" styles={{backgroundImage: `url(${module.images[0]})`}}></div>
                      <div className="result-score">{module.score}</div>
                    </Link>
                  </li>
                ))
              }
            </ul>
          </div>
          : <div className="subheader">I couldn{'\''}t find any modules matching your search. Try again?</div>
        )
    const projectSearchResults = (searchProjects.length > 0 ? (
          <div className="project-results">
            <div className="subheader">Do these projects match your search?</div>
            <ul className="results-list">
              {
                searchProjects.map(project => (
                  <li key={project.pid}>
                    <Link to={`/projects/${project.pid}`}>
                      <div className="result-title">{project.title}</div>
                      <div className="result-image" styles={{backgroundImage: `url(${project.images[0]})`}}></div>
                      <div className="result-score">{project.score}</div>
                    </Link>
                  </li>
                ))
              }
            </ul>
          </div>
          : <div className="subheader">I couldn{'\''}t find any projects matching your search. Try again?</div>
        )
    return (
      <div className="container">
      {
        (calmed
          ? (
            <div className="index">
              <div className="header">What do you want to do?</div>
              <div className="guide-search">
                <input className="search" onKeyPress={this.submitSearch} placeholder="Press enter to search" />
                <div>
                  <Link to="/modules/create"><button className="create-link">New Module</button></Link>
                  <Link to="/projects/create"><button className="create-link">New Project</button></Link>
                </div>
              </div>
              {moduleSearchResults}
              {projectSearchResults}
            </div>
          )
          : (
            <div className="intro">Don{'\''}t Panic</div>
          )
        )
      }
      </div>
    );
  }
}

const mapDispatch = (dispatch) => ({
  guideSearch,
});

const mapState = (state) => ({
  modules: state.moduleSearchResults,
  projectSearchResults: state.projectSearchResults,
});

export default connect(mapState)(mapDispatch)(Index);
