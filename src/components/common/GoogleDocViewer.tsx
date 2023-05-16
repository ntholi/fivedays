import React from 'react';

type Props = {
  fileId?: string | null;
};

const GoogleDocViewer = ({ fileId }: Props) => {
  if (!fileId) return null;

  return (
    <iframe
      src={`https://drive.google.com/file/d/${fileId}/preview`}
      style={{ width: '100%', height: '100%' }}
      frameBorder='0'
    ></iframe>
  );
};

export default GoogleDocViewer;
