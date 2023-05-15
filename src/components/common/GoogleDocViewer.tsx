import React from 'react';

type Props = {
  url?: string;
};

const GoogleDocViewer = ({ url }: Props) => {
  if (!url) return null;

  const fileId = url.split('/d/')[1].split('/view')[0];
  const directLink = `https://drive.google.com/uc?export=download&id=${fileId}`;

  return (
    <iframe
      src={`https://docs.google.com/viewer?url=${directLink}&embedded=true`}
      style={{ width: '100%', height: '1000px' }}
      frameBorder='0'
    ></iframe>
  );
};

export default GoogleDocViewer;
