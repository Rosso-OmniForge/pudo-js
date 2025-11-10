/**
 * Pudo Configuration Utility
 * Validates and provides environment configuration
 * 
 * Copyright: © 2025
 */

import type { PudoConfig } from './types';

/**
 * Get and validate Pudo configuration from environment variables
 * Throws descriptive errors if configuration is missing
 */
export function getPudoConfig(): PudoConfig {
  const apiKey = process.env.PUDO_API_KEY;
  const apiUrl = process.env.PUDO_API_URL;

  if (!apiKey) {
    throw new Error(
      'PUDO_API_KEY environment variable is required. ' +
      'Please add it to your .env.local file:\n\n' +
      'PUDO_API_KEY=your_api_key_here\n\n' +
      'Get your API key from: https://pudo.co.za'
    );
  }

  // Validate API key format (basic check)
  if (apiKey.length < 10) {
    throw new Error(
      'PUDO_API_KEY appears to be invalid (too short). ' +
      'Please check your API key from the Pudo dashboard.'
    );
  }

  return {
    apiKey,
    apiUrl,
    isDevelopment: process.env.NODE_ENV === 'development',
  };
}

/**
 * Check if Pudo is properly configured
 * Returns true if config is valid, false otherwise
 */
export function isPudoConfigured(): boolean {
  try {
    getPudoConfig();
    return true;
  } catch {
    return false;
  }
}
