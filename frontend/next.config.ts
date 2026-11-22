import type { NextConfig } from "next";

// The Laravel API, e.g. http://backend.biponon.local/api/v1 locally or
// https://saas-backend.mypersonalwork.com/api/v1 in production. Product photos are
// served by the same Laravel app under /storage, so next/image may load them.
// Read at BUILD time: after changing BIPONON_API_URL on Vercel, redeploy.
const apiUrl = new URL(process.env.BIPONON_API_URL ?? "http://localhost:8000/api/v1");

const nextConfig: NextConfig = {
  cacheComponents: true,
  partialPrefetching: true,
  images: {
    remotePatterns: [new URL(`${apiUrl.origin}/storage/**`)],
    // Locally the Laravel host resolves to 127.0.0.1, which next/image refuses
    // by default (it protects servers from being tricked into fetching internal
    // addresses). Allowed in development only.
    dangerouslyAllowLocalIP: process.env.NODE_ENV === "development",
  },
  turbopack: {
    rules: {
      "*.css": {
        loaders: ["@tailwindcss/turbopack"],
        as: "*.css",
      },
    },
  },
};

export default nextConfig;
