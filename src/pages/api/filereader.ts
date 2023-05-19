import { NextApiRequest, NextApiResponse } from 'next';
import { getSession } from 'next-auth/react';
import axios from 'axios';

export default async function handler(
  req: NextApiRequest,
  res: NextApiResponse
) {
  const session = await getSession({ req });
  if (!session) {
    return res.status(401).json({ error: 'Unauthorized' });
  }
  const fileId = req.query.fileId;
  if (!fileId) {
    return res.status(400).json({ error: 'Missing "fileId" query parameter.' });
  }

  const fileContent = await readFile(fileId, session);
  res.status(200).send(fileContent);
}

export async function readFile(fileId: string | string[], session: any) {
  const fileUrl = `https://www.googleapis.com/drive/v3/files/${fileId}?alt=media`;
  const response = await axios.get(fileUrl, {
    headers: { Authorization: `Bearer ${session.accessToken}` },
    responseType: 'text',
  });
  return response.data;
}
