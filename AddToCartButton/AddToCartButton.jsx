import React from 'react'
import CSSModules from 'react-css-modules'
import PropTypes from 'prop-types'

import styles from './AddToCartButton.css'

class AddToCartButton extends React.Component {
    static contextTypes = {
        userId: PropTypes.number
    };

    constructor(props) {
        super(props)

        this.state = {
            hover: false
        }
    }

    render() {
        let label = 'ADD TO CART'
        let buttonStyle = 'disabled'
        if (this.context.userId) {
            buttonStyle = 'ready'
            if (this.props.added) {
                label = "REMOVE FROM CART"
            } else {
                label = "ADD TO CART"
            }
        }
        return (
            <div styleName={buttonStyle}
                onClick={this.props.clickHandler}
                onMouseOver={(e) => this.setState({ hover: true })}
                onMouseLeave={(e) => this.setState({ hover: false })}
            >
                {label}
            </div>
            )
    }
}
export default CSSModules(AddToCartButton, styles)
