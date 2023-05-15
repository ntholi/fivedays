import {
  ActionIcon,
  Button,
  Container,
  Divider,
  Flex,
  Group,
  NumberInput,
  Stack,
  TextInput,
  Title,
  Table,
  Text,
  LoadingOverlay,
} from '@mantine/core';
import { IconTrash } from '@tabler/icons-react';
import React, { useEffect } from 'react';
import axios from 'axios';
import RubricForm from './RubricForm';
import { classroom_v1 } from 'googleapis';

type Props = {
  assessment: classroom_v1.Schema$CourseWork;
  close: () => void;
};

export default function RubricDisplay({ assessment, close }: Props) {
  const [data, setData] = React.useState<Rubric[]>([]);
  const [saving, setSaving] = React.useState(false);
  const [loading, setLoading] = React.useState(false);

  // TODO: handle null or undefined questionId

  useEffect(() => {
    async function getRubric() {
      try {
        setLoading(true);
        const response = await axios.get('/api/rubric/read', {
          params: { questionId: assessment.id },
        });
        console.log('Response', response.data);
        setData(response.data);
      } catch (ex) {
        console.log(ex);
      } finally {
        setLoading(false);
      }
    }
    getRubric();
  }, []);

  const submitRubric = async () => {
    try {
      setSaving(true);
      const response = await axios.post('/api/rubric/create', {
        questionId: assessment.id,
        data: data,
      });
      close();
    } catch (ex) {
      console.log(ex);
    } finally {
      setSaving(false);
    }
  };

  function deleteItem(rubric: Rubric) {
    setData(data.filter((it) => it.title !== rubric.title));
  }

  return (
    <>
      <LoadingOverlay visible={loading} overlayBlur={2} />
      <Title order={3} fw='normal'>
        <Flex justify='space-between'>
          <Text>Rubric</Text>
          <Button onClick={submitRubric} loading={saving}>
            Save
          </Button>
        </Flex>
      </Title>
      <Divider my='sm' />
      <Container size='lg'>
        <RubricForm assessment={assessment} setData={setData} data={data} />
        <Table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Points</th>
              <th>Description</th>
            </tr>
          </thead>
          <tbody>
            {data.map((it) => (
              <tr key={it.title}>
                <td>{it.title}</td>
                <td>{it.points}</td>
                <td>{it.description}</td>
                <td>
                  <ActionIcon color='red' onClick={() => deleteItem(it)}>
                    <IconTrash size='1.125rem' />
                  </ActionIcon>
                </td>
              </tr>
            ))}
          </tbody>
        </Table>
      </Container>
    </>
  );
}
