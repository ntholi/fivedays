import React from 'react';

type Props = {
  url?: string | null;
};

const GoogleDocViewer = ({ url }: Props) => {
  if (!url) return null;

  const fileId = url.split('/d/')[1].split('/view')[0];
  const directLink = `https://drive.google.com/uc?export=download&id=${fileId}`;

  return (
    <iframe
      src={`https://drive.google.com/file/d/${fileId}/preview`}
      style={{ width: '100%', height: '100%' }}
      frameBorder='0'
    ></iframe>
  );
};

export default GoogleDocViewer;
