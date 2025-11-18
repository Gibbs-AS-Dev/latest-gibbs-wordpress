const fs = require('fs');
const path = require('path');
const JavaScriptObfuscator = require('javascript-obfuscator');

// Specify the file paths
const inputFilePath = path.join(__dirname, '', 'script.js');  // The original JS file to obfuscate
const outputFilePath = path.join(__dirname, '', 'widget.min.js');  // Output obfuscated JS file

// Read the JavaScript file to obfuscate
const inputCode = fs.readFileSync(inputFilePath, 'utf-8');

// Obfuscate the JavaScript code
const obfuscatedCode = JavaScriptObfuscator.obfuscate(inputCode, {
    compact: true,
    controlFlowFlattening: true, // Optional: Add more options to make the code harder to reverse-engineer
    deadCodeInjection: true,
    stringArray: true,
    stringArrayEncoding: ['base64']
}).getObfuscatedCode();

// Write the obfuscated code to the output file
fs.writeFileSync(outputFilePath, obfuscatedCode);

console.log('Obfuscation complete! Output file: ' + outputFilePath);
