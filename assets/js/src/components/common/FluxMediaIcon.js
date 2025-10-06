import React from 'react';

/**
 * Flux Media brand icon component
 * 
 * @since 1.0.0
 */
const FluxMediaIcon = ({ size = 32, ...props }) => {
  return (
    <svg
      xmlns="http://www.w3.org/2000/svg"
      viewBox="0 0 200 200"
      width={size}
      height={size}
      aria-labelledby="title desc"
      role="img"
      {...props}
    >
      <title id="title">Flux Media Logo</title>
      <desc id="desc">Minimalist abstract icon with blue-to-teal gradient outline of an inverted trapezoidal shape with internal geometric divisions.</desc>

      <defs>
        {/* Blue to teal gradient for the outline */}
        <linearGradient id="fluxGradient" x1="0%" y1="0%" x2="0%" y2="100%">
          <stop offset="0%" stopColor="#00BFFF" stopOpacity="1"/>
          <stop offset="50%" stopColor="#20B2AA" stopOpacity="1"/>
          <stop offset="100%" stopColor="#00CED1" stopOpacity="1"/>
        </linearGradient>
      </defs>

      {/* Main inverted trapezoidal shape with rounded top corners */}
      <path
        d="M 40 60 
           L 160 60 
           L 160 65 
           L 150 140 
           L 50 140 
           L 40 65 
           Z"
        fill="none"
        stroke="url(#fluxGradient)"
        strokeWidth="3"
        strokeLinecap="round"
        strokeLinejoin="round"
      />

      {/* Central vertical line */}
      <line
        x1="100"
        y1="65"
        x2="100"
        y2="140"
        stroke="url(#fluxGradient)"
        strokeWidth="2"
        strokeLinecap="round"
      />

      {/* Left diagonal line from top-left corner to center */}
      <line
        x1="50"
        y1="65"
        x2="100"
        y2="100"
        stroke="url(#fluxGradient)"
        strokeWidth="2"
        strokeLinecap="round"
      />

      {/* Right diagonal line from top-right corner to center */}
      <line
        x1="150"
        y1="65"
        x2="100"
        y2="100"
        stroke="url(#fluxGradient)"
        strokeWidth="2"
        strokeLinecap="round"
      />
    </svg>
  );
};

export default FluxMediaIcon;
