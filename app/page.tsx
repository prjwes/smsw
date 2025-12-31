"use client"

import { useEffect } from "react"

export default function SyntheticV0PageForDeployment() {
  useEffect(() => {
    window.location.href = "/"
  }, [])

  return (
    <div
      style={{
        display: "flex",
        justifyContent: "center",
        alignItems: "center",
        height: "100vh",
        fontFamily: "system-ui, -apple-system, sans-serif",
      }}
    >
      <div style={{ textAlign: "center" }}>
        <h2>Loading School Management System...</h2>
        <p style={{ color: "#666" }}>Please wait while the application loads.</p>
      </div>
    </div>
  )
}
